<?php

namespace Tests\Feature\StaffHR;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\StaffHR\Models\Staff;
use App\Modules\StaffHR\Models\StaffDisciplinaryLog;
use App\Modules\StaffHR\Models\StaffDocument;
use App\Modules\StaffHR\Models\StaffExitRecord;
use App\Modules\StaffHR\Models\StaffSalaryHistory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeadStaffScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_sees_each_link_path_and_never_sees_excluded_or_out_of_scope_staff(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['head'])->get(route('head.staff.index'))
            ->assertOk()
            ->assertSee('Direct Staff')
            ->assertSee('Subject Staff')
            ->assertSee('Form Staff')
            ->assertDontSee('Outside Staff')
            ->assertDontSee('School Admin')
            ->assertDontSee('Wide Staff')
            ->assertDontSee('Other Head')
            ->assertDontSee('Exited Staff');

        foreach (['outside', 'adminStaff', 'wideStaff', 'otherHeadStaff', 'headStaff', 'exited'] as $key) {
            $this->get(route('head.staff.show', $f[$key]))->assertNotFound();
        }
        $this->get(route('head.staff.show', $f['direct']))->assertOk()->assertSee('Direct Staff');
    }

    public function test_head_with_two_sections_sees_the_union_and_search_is_scoped(): void
    {
        $f = $this->fixture();
        $f['head']->headSections()->attach($f['secondary'], ['school_id' => $f['school']->id]);

        $this->actingAs($f['head'])->get(route('head.staff.index'))
            ->assertOk()->assertSee('Direct Staff')->assertSee('Outside Staff');
        $this->get(route('head.staff.index', ['q' => 'Outside']))
            ->assertOk()->assertSee('Outside Staff')->assertDontSee('Direct Staff');
    }

    public function test_no_section_and_missing_permissions_reveal_no_staff(): void
    {
        $f = $this->fixture();
        $headWithoutSection = $this->user('Unassigned Head', $f['principalRole']);

        $response = $this->actingAs($headWithoutSection)->get(route('head.staff.index'))
            ->assertOk()->assertSee('No Section assigned')->assertDontSee('Direct Staff');
        $this->assertNull($response->viewData('staff'));
        $this->get(route('head.staff.show', $f['direct']))->assertNotFound();

        $this->actingAs($f['teacher'])->get(route('head.staff.index'))->assertForbidden();
        $this->actingAs($f['bursar'])->get(route('head.staff.index'))->assertForbidden();
    }

    public function test_detail_is_allow_listed_and_sensitive_routes_remain_forbidden(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        StaffSalaryHistory::create(['staff_id' => $f['direct']->id, 'amount_kobo' => 987654321, 'effective_from' => '2026-01-01']);
        StaffDocument::create(['staff_id' => $f['direct']->id, 'document_type' => 'Secret contract', 'file_path' => 'private/secret.pdf', 'uploaded_at' => now()]);
        StaffExitRecord::create(['staff_id' => $f['direct']->id, 'exit_type' => 'Resigned', 'exit_date' => '2025-01-01']);
        StaffDisciplinaryLog::create(['staff_id' => $f['direct']->id, 'type' => 'Warning', 'date' => '2026-01-02', 'description' => 'Recorded conduct note', 'issued_by' => $f['admin']->id]);

        $response = $this->actingAs($f['head'])->get(route('head.staff.show', $f['direct']))
            ->assertOk()->assertSee('Recorded conduct note')
            ->assertDontSee('987654321')->assertDontSee('Secret contract')->assertDontSee('Resigned');
        $this->assertSame(
            ['id', 'name', 'email', 'position', 'roles', 'sections', 'teaching'],
            array_keys($response->viewData('profile')),
        );
        $this->assertDatabaseHas('staff_sensitive_view_audit', [
            'staff_id' => $f['direct']->id, 'viewed_by' => $f['head']->id, 'data_type' => 'Disciplinary',
        ]);

        $this->get(route('staff.salary', $f['direct']))->assertForbidden();
        $this->get(route('staff.documents.download', [$f['direct'], 1]))->assertForbidden();
        $this->get(route('staff.show', $f['direct']))->assertForbidden();
        $this->get(route('staff.edit', $f['direct']))->assertForbidden();
    }

    public function test_head_records_append_only_discipline_in_scope_and_audits_it(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 10:00', 'Africa/Lagos'));
        $f = $this->fixture();
        $data = ['type' => 'Commendation', 'date' => '2026-11-04', 'description' => 'Helped the Section', 'resolution' => 'Noted'];

        $this->actingAs($f['head'])->post(route('head.staff.discipline.store', $f['direct']), $data)
            ->assertRedirect(route('head.staff.show', $f['direct']));
        app(TenantContext::class)->set($f['school']->id);
        $entry = StaffDisciplinaryLog::where('staff_id', $f['direct']->id)->firstOrFail();
        $this->assertSame($f['head']->id, $entry->issued_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'section_staff_discipline_recorded',
            'user_id' => $f['head']->id,
            'actor_name' => 'Current Head',
            'subject_id' => $entry->id,
        ]);
        $this->post(route('head.staff.discipline.store', $f['outside']), $data)->assertNotFound();
        $this->post(route('head.staff.discipline.store', $f['direct']), array_merge($data, ['date' => '2026-11-05']))
            ->assertSessionHasErrors('date');

        app(TenantContext::class)->set($f['school']->id);
        $viewOnlyRole = Role::create(['name' => 'Section Viewer']);
        $viewOnlyRole->permissions()->attach(Permission::where('name', 'view_section_staff')->firstOrFail());
        $viewer = $this->user('View Only', $viewOnlyRole);
        $viewer->headSections()->attach($f['primary'], ['school_id' => $f['school']->id]);
        $this->actingAs($viewer)->post(route('head.staff.discipline.store', $f['direct']), $data)->assertForbidden();
    }

    public function test_permission_migration_adds_head_permissions_without_granting_other_roles(): void
    {
        $school = School::create(['name' => 'Existing School']);
        app(TenantContext::class)->set($school->id);
        $principal = Role::create(['name' => 'Head']);
        $teacher = Role::create(['name' => 'Teacher']);
        $custom = Permission::firstOrCreate(['name' => 'custom_permission']);
        $principal->permissions()->attach($custom);

        $migration = require app_path('Modules/Core/Database/Migrations/2026_11_04_000400_grant_head_staff_permissions.php');
        $migration->up();

        $this->assertTrue($principal->permissions()->whereIn('name', ['view_section_staff', 'record_section_discipline'])->count() === 2);
        $this->assertTrue($principal->permissions()->where('name', 'custom_permission')->exists());
        $this->assertFalse($teacher->permissions()->whereIn('name', ['view_section_staff', 'record_section_discipline'])->exists());
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $principalRole = $roles['Head'];
        $teacherRole = $roles['Teacher'];
        $bursarRole = $roles['Bursar'];
        $adminRole = $roles['School Admin'];
        $head = $this->user('Current Head', $principalRole);
        $teacher = $this->user('Plain Teacher', $teacherRole);
        $bursar = $this->user('Plain Bursar', $bursarRole);
        $admin = $this->user('School Admin', $adminRole);
        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $head->headSections()->attach($primary, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary 4']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'JS 1']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 4']);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'JS 1']);
        $subject = Subject::create(['section_id' => $primary->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $primaryClass->id, 'subject_id' => $subject->id]);

        $directUser = $this->user('Direct Staff', $teacherRole);
        $directUser->sections()->attach($primary, ['school_id' => $school->id]);
        $direct = $this->staff($directUser, 'Classroom assistant');
        $subjectUser = $this->user('Subject Staff', $teacherRole);
        $subjectStaff = $this->staff($subjectUser, 'Subject teacher');
        TeacherSubjectAssignment::create(['user_id' => $subjectUser->id, 'class_id' => $primaryClass->id, 'subject_id' => $subject->id]);
        $formUser = $this->user('Form Staff', $teacherRole);
        $formStaff = $this->staff($formUser, 'Form teacher');
        ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'Gold', 'form_teacher_user_id' => $formUser->id]);
        $outsideUser = $this->user('Outside Staff', $teacherRole);
        $outsideUser->sections()->attach($secondary, ['school_id' => $school->id]);
        $outside = $this->staff($outsideUser, 'Secondary teacher');
        $adminStaff = $this->staff($admin, 'Administrator');
        $wideRole = Role::create(['name' => 'Wide Viewer']);
        $wideRole->permissions()->attach(Permission::where('name', 'view_all_sections')->firstOrFail());
        $wideUser = $this->user('Wide Staff', $wideRole);
        $wideUser->sections()->attach($primary, ['school_id' => $school->id]);
        $wideStaff = $this->staff($wideUser, 'Wide viewer');
        $otherHead = $this->user('Other Head', $principalRole);
        $otherHead->headSections()->attach($primary, ['school_id' => $school->id]);
        $otherHeadStaff = $this->staff($otherHead, 'Other head');
        $headStaff = $this->staff($head, 'Head');
        $exitedUser = $this->user('Exited Staff', $teacherRole);
        $exitedUser->sections()->attach($primary, ['school_id' => $school->id]);
        $exited = $this->staff($exitedUser, 'Former teacher', 'Resigned');

        return compact('school', 'principalRole', 'head', 'teacher', 'bursar', 'admin', 'primary', 'secondary', 'primaryClass', 'secondaryClass', 'direct', 'subjectStaff', 'formStaff', 'outside', 'adminStaff', 'wideStaff', 'otherHeadStaff', 'headStaff', 'exited');
    }

    private function user(string $name, Role $role): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().'@head-staff.test', 'password' => 'password', 'status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }

    private function staff(User $user, string $position, string $status = 'Active'): Staff
    {
        return Staff::create(['user_id' => $user->id, 'role_title' => $position, 'employment_date' => '2025-01-01', 'contract_type' => 'Permanent', 'status' => $status]);
    }
}
