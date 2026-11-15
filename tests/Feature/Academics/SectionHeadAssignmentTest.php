<?php

namespace Tests\Feature\Academics;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionHeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_picker_contains_only_active_staff_accounts(): void
    {
        $fixture = $this->fixture();
        $principal = $this->user('Active Head', 'principal@example.test', $fixture['principalRole']);
        $teacher = $this->user('Active Teacher', 'teacher@example.test', $fixture['teacherRole']);
        $this->user('Active Bursar', 'bursar@example.test', $fixture['bursarRole']);
        $this->user('Portal Student', 'student@example.test', $fixture['studentRole']);
        $this->user('Portal Parent', 'parent@example.test', $fixture['parentRole']);
        $this->user('Portal Candidate', 'candidate@example.test', $fixture['candidateRole']);
        $disabled = $this->user('Disabled Teacher', 'disabled@example.test', $fixture['teacherRole']);
        $disabled->update(['status' => User::STATUS_DISABLED]);

        $this->actingAs($fixture['admin'])->get(route('academics.index'))
            ->assertOk()
            ->assertSee('Search active staff')
            ->assertSee($principal->name)
            ->assertSee($teacher->name)
            ->assertSee('Active Bursar')
            ->assertDontSee('Portal Student')
            ->assertDontSee('Portal Parent')
            ->assertDontSee('Portal Candidate')
            ->assertDontSee('Disabled Teacher');
    }

    public function test_assigning_and_removing_a_head_preserves_roles_scopes_and_audits_changes(): void
    {
        $fixture = $this->fixture();
        $teacher = $this->user('Teacher Head', 'teacher-head@example.test', $fixture['teacherRole']);
        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher', 'learner_term' => 'Pupil']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal', 'learner_term' => 'Student']);

        $this->actingAs($fixture['admin'])->put(route('academics.sections.update', $primary), $this->sectionPayload($primary, $teacher))
            ->assertRedirect(route('academics.index'));
        $this->put(route('academics.sections.update', $secondary), $this->sectionPayload($secondary, $teacher))
            ->assertRedirect(route('academics.index'));

        app(TenantContext::class)->set($fixture['school']->id);
        $teacher->refresh();
        $this->assertEqualsCanonicalizing(['Head', 'Teacher'], $teacher->roles()->pluck('name')->all());
        $this->assertEqualsCanonicalizing([$primary->id, $secondary->id], $teacher->sections()->pluck('sections.id')->all());
        $this->assertEqualsCanonicalizing([$primary->id, $secondary->id], $teacher->headSections()->pluck('sections.id')->all());
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'section_head_assigned', 'subject_id' => $primary->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'section_head_assigned', 'subject_id' => $secondary->id]);

        $this->put(route('academics.sections.update', $primary), $this->sectionPayload($primary))
            ->assertRedirect(route('academics.index'));
        app(TenantContext::class)->set($fixture['school']->id);
        $this->assertTrue($teacher->fresh()->roles()->where('name', 'Head')->exists());
        $this->assertFalse($teacher->fresh()->headSections()->whereKey($primary->id)->exists());
        $this->assertTrue($teacher->fresh()->sections()->whereKey($primary->id)->exists());

        $this->put(route('academics.sections.update', $secondary), $this->sectionPayload($secondary))
            ->assertRedirect(route('academics.index'));
        app(TenantContext::class)->set($fixture['school']->id);
        $teacher->refresh();
        $this->assertTrue($teacher->roles()->where('name', 'Teacher')->exists());
        $this->assertFalse($teacher->roles()->where('name', 'Head')->exists());
        $this->assertFalse($teacher->headSections()->exists());
        $this->assertTrue($teacher->sections()->exists());
        $this->assertDatabaseCount('audit_logs', 4);
        $this->assertDatabaseHas('audit_logs', ['action' => 'section_head_removed', 'subject_id' => $primary->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'section_head_removed', 'subject_id' => $secondary->id]);
    }

    public function test_user_without_section_management_permission_cannot_assign_a_head(): void
    {
        $fixture = $this->fixture();
        $teacher = $this->user('Ordinary Teacher', 'ordinary@example.test', $fixture['teacherRole']);
        $section = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher', 'learner_term' => 'Pupil']);

        $this->actingAs($teacher)->put(route('academics.sections.update', $section), $this->sectionPayload($section, $teacher))
            ->assertForbidden();

        app(TenantContext::class)->set($fixture['school']->id);
        $this->assertFalse($section->fresh()->heads()->exists());
        $this->assertFalse($teacher->fresh()->roles()->where('name', 'Head')->exists());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $manageAcademics = Permission::firstOrCreate(['name' => 'manage_academics']);
        $viewAllSections = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([$manageAcademics->id, $viewAllSections->id]);
        $principalRole = Role::create(['name' => 'Head']);
        $teacherRole = Role::create(['name' => 'Teacher']);
        $bursarRole = Role::create(['name' => 'Bursar']);
        $studentRole = Role::create(['name' => 'Student']);
        $parentRole = Role::create(['name' => 'Parent']);
        $candidateRole = Role::create(['name' => 'Candidate']);
        $admin = $this->user('School Admin', 'admin@example.test', $adminRole);

        return compact('school', 'admin', 'principalRole', 'teacherRole', 'bursarRole', 'studentRole', 'parentRole', 'candidateRole');
    }

    private function user(string $name, string $email, Role $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'long-password-here',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    private function sectionPayload(Section $section, ?User $head = null): array
    {
        return array_filter([
            'name' => $section->name,
            'head_title' => $section->head_title,
            'learner_term' => $section->learner_term,
            'head_user_id' => $head?->id,
        ], fn ($value): bool => $value !== null);
    }
}
