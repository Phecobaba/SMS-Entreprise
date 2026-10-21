<?php

namespace Tests\Feature\Academics;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Academics\Services\AcademicAssignmentManager;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_scope_rejects_edited_ids_admin_can_act_and_head_cannot_open_results_configuration(): void
    {
        $f = $this->fixture();
        $payload = [
            'section_id' => $f['primary']->id, 'level_id' => $f['primaryLevel']->id,
            'class_ids' => [$f['secondaryClass']->id], 'subject_ids' => [$f['math']->id],
        ];

        $this->actingAs($f['head'])->post(route('academics.bulk-subjects.review'), $payload)->assertForbidden();
        $this->get(route('results.configuration'))->assertForbidden();

        $adminPayload = [
            'section_id' => $f['secondary']->id, 'level_id' => $f['secondaryLevel']->id,
            'class_ids' => [$f['secondaryClass']->id], 'subject_ids' => [$f['english']->id],
        ];
        $this->actingAs($f['admin'])->post(route('academics.bulk-subjects.review'), $adminPayload)
            ->assertOk()->assertSee('Will add');
        $this->post(route('academics.bulk-subjects.apply'), $adminPayload)->assertRedirect();
        $this->assertDatabaseHas('class_subjects', ['class_id' => $f['secondaryClass']->id, 'subject_id' => $f['english']->id]);
    }

    public function test_scope_routes_removal_and_admin_access_are_enforced(): void
    {
        $f = $this->fixture();
        $outside = TeacherSubjectAssignment::create(['user_id' => $f['teacher']->id, 'class_id' => $f['secondaryClass']->id, 'subject_id' => $f['math']->id]);

        $this->actingAs($f['head'])->get(route('academics.teacher-assignments.index', ['section' => $f['secondary']->id]))->assertForbidden();
        $this->post(route('academics.teacher-assignments.review'), [
            'section_id' => $f['secondary']->id, 'mode' => 'class', 'class_id' => $f['secondaryClass']->id,
            'teachers' => [$f['math']->id => ''],
        ])->assertForbidden();
        $this->get(route('results.configuration'))->assertForbidden();
        $this->assertDatabaseHas('teacher_subject_assignments', ['id' => $outside->id]);

        $this->actingAs($f['admin'])->post(route('academics.teacher-assignments.review'), [
            'section_id' => $f['secondary']->id, 'mode' => 'class', 'class_id' => $f['secondaryClass']->id,
            'teachers' => [$f['math']->id => ''],
        ])->assertOk()->assertSee('Will remove');
        $this->post(route('academics.teacher-assignments.apply'), [
            'section_id' => $f['secondary']->id, 'mode' => 'class', 'class_id' => $f['secondaryClass']->id,
            'teachers' => [$f['math']->id => ''],
        ])->assertRedirect();
        $this->assertDatabaseMissing('teacher_subject_assignments', ['id' => $outside->id]);
    }

    public function test_bulk_subject_assignment_is_idempotent_and_preview_matches_apply(): void
    {
        $f = $this->fixture();
        $service = app(AcademicAssignmentManager::class);
        $classIds = [$f['primaryClass']->id, $f['primaryTwo']->id];
        $subjectIds = [$f['math']->id, $f['english']->id];

        $preview = $service->previewBulkSubjects($f['head'], $f['primary']->id, $f['primaryLevel']->id, null, $classIds, $subjectIds);
        $applied = $service->applyBulkSubjects($f['head'], $f['primary']->id, $f['primaryLevel']->id, null, $classIds, $subjectIds);
        $this->assertSame($preview['added'], $applied['added']);
        $this->assertSame($preview['skipped'], $applied['skipped']);
        $this->assertSame(3, $applied['added']);
        $this->assertDatabaseCount('class_subjects', 5);

        $repeatPreview = $service->previewBulkSubjects($f['head'], $f['primary']->id, $f['primaryLevel']->id, null, $classIds, $subjectIds);
        $repeat = $service->applyBulkSubjects($f['head'], $f['primary']->id, $f['primaryLevel']->id, null, $classIds, $subjectIds);
        $this->assertSame(0, $repeatPreview['added']);
        $this->assertSame($repeatPreview['added'], $repeat['added']);
        $this->assertSame(4, $repeat['skipped']);
        $this->assertDatabaseCount('class_subjects', 5);
    }

    public function test_teacher_preview_matches_apply_and_non_class_subject_pairs_are_ignored(): void
    {
        $f = $this->fixture();
        $service = app(AcademicAssignmentManager::class);
        $pairs = [
            $f['primaryClass']->id.':'.$f['math']->id,
            $f['primaryClass']->id.':'.$f['english']->id,
        ];

        $preview = $service->previewTeacher($f['head'], $f['primary']->id, $f['teacher']->id, $pairs);
        $applied = $service->applyTeacher($f['head'], $f['primary']->id, $f['teacher']->id, $pairs);
        $this->assertSame($preview['added'], $applied['added']);
        $this->assertSame($preview['removed'], $applied['removed']);
        $this->assertSame(1, $applied['added']);
        $this->assertDatabaseHas('teacher_subject_assignments', ['class_id' => $f['primaryClass']->id, 'subject_id' => $f['math']->id]);
        $this->assertDatabaseMissing('teacher_subject_assignments', ['class_id' => $f['primaryClass']->id, 'subject_id' => $f['english']->id]);
    }

    public function test_removal_preserves_results(): void
    {
        $f = $this->fixture();
        $assignment = TeacherSubjectAssignment::create(['user_id' => $f['teacher']->id, 'class_id' => $f['primaryClass']->id, 'subject_id' => $f['math']->id]);
        $arm = ClassArm::create(['class_id' => $f['primaryClass']->id, 'name' => 'Gold']);
        $studentUser = User::create(['name' => 'Student', 'email' => 'assignment-student@example.test', 'password' => 'password-long', 'status' => 'active']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-09-01']);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $scheme = GradingScheme::create(['name' => 'Default']);
        $template = ResultTemplate::create(['academic_level_id' => $f['primaryLevel']->id, 'grading_scheme_id' => $scheme->id, 'name' => 'Primary', 'scope_key' => 'level:'.$f['primaryLevel']->id]);
        $result = Result::create(['student_id' => $student->id, 'class_arm_id' => $arm->id, 'subject_id' => $f['math']->id, 'term_id' => $term->id, 'result_template_id' => $template->id, 'status' => 'Draft']);

        $service = app(AcademicAssignmentManager::class);
        $preview = $service->previewTeacher($f['head'], $f['primary']->id, $f['teacher']->id, []);
        $applied = $service->applyTeacher($f['head'], $f['primary']->id, $f['teacher']->id, []);
        $this->assertSame($preview['removed'], $applied['removed']);
        $this->assertSame(1, $applied['removed']);
        $this->assertDatabaseMissing('teacher_subject_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('results', ['id' => $result->id, 'student_id' => $student->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $head = User::create(['name' => 'Primary Head', 'email' => 'assignment-head@example.test', 'password' => 'password-long', 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'assignment-admin@example.test', 'password' => 'password-long', 'status' => 'active']);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'assignment-teacher@example.test', 'password' => 'password-long', 'status' => 'active']);
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach([
            Permission::firstOrCreate(['name' => AcademicAssignmentManager::SUBJECT_PERMISSION]),
            Permission::firstOrCreate(['name' => AcademicAssignmentManager::TEACHER_PERMISSION]),
        ]);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([
            Permission::firstOrCreate(['name' => 'manage_academics']),
            Permission::firstOrCreate(['name' => 'manage_result_settings']),
            Permission::firstOrCreate(['name' => 'view_all_sections']),
        ]);
        $teacherRole = Role::create(['name' => 'Teacher']);
        $teacherRole->permissions()->attach(Permission::firstOrCreate(['name' => 'enter_results']));
        $head->roles()->attach($headRole);
        $admin->roles()->attach($adminRole);
        $teacher->roles()->attach($teacherRole);

        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Secondary']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 1', 'position' => 1]);
        $primaryTwo = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 2', 'position' => 2]);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'JS1', 'position' => 3]);
        $math = Subject::create(['name' => 'Mathematics']);
        $english = Subject::create(['name' => 'English']);
        ClassSubject::create(['class_id' => $primaryClass->id, 'subject_id' => $math->id]);
        ClassSubject::create(['class_id' => $secondaryClass->id, 'subject_id' => $math->id]);

        return compact('school', 'head', 'admin', 'teacher', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel', 'primaryClass', 'primaryTwo', 'secondaryClass', 'math', 'english');
    }
}
