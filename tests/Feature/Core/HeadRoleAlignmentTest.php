<?php

namespace Tests\Feature\Core;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\HeadAssignments;
use App\Modules\Academics\Services\PromotionCatalog;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use App\Modules\StaffHR\Services\HeadStaffScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadRoleAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Section $primary;

    private Section $secondary;

    private array $roles;

    protected function setUp(): void
    {
        parent::setUp();
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $this->roles = app(DefaultRoleCatalog::class)->provision()->all();
        $this->admin = $this->user('Admin', $this->roles['School Admin']);
        $this->teacher = $this->user('Teacher Head', $this->roles['Teacher']);
        $this->primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher', 'classes_arranged_at' => now(), 'classes_arranged_by' => $this->admin->id]);
        $this->secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $this->teacher->sections()->attach([
            $this->primary->id => ['school_id' => $school->id],
            $this->secondary->id => ['school_id' => $school->id],
        ]);
    }

    public function test_work_membership_does_not_widen_head_scope_and_both_entry_points_sync_the_role(): void
    {
        app(HeadAssignments::class)->setSectionHead($this->primary, $this->teacher, $this->admin);

        $this->assertEqualsCanonicalizing([$this->primary->id], app(SectionAccess::class)->sectionIds($this->teacher));
        $this->assertEqualsCanonicalizing([$this->primary->id], app(HeadStaffScope::class)->sectionIds($this->teacher)->all());
        $this->assertTrue($this->teacher->fresh()->roles()->where('name', 'Head')->exists());
        $this->assertEqualsCanonicalizing([$this->primary->id, $this->secondary->id], $this->teacher->sections()->pluck('sections.id')->all());

        $automatic = Role::create(['name' => 'Automatic helper']);
        $this->teacher->roles()->attach($automatic);
        $this->actingAs($this->admin)->put(route('staff.access.update', $this->teacher), [
            'role_id' => $this->roles['Teacher']->id,
            'section_ids' => [$this->primary->id, $this->secondary->id],
            'head_sections_present' => 1,
            'head_section_ids' => [$this->primary->id, $this->secondary->id],
        ])->assertRedirect(route('staff.access.show', $this->teacher));
        app(TenantContext::class)->set($this->admin->school_id);
        $this->assertEqualsCanonicalizing(['Automatic helper', 'Head', 'Teacher'], $this->teacher->fresh()->roles()->pluck('name')->all());

        $this->put(route('staff.access.update', $this->teacher), [
            'role_id' => $this->roles['Teacher']->id,
            'section_ids' => [$this->primary->id, $this->secondary->id],
            'head_sections_present' => 1,
            'head_section_ids' => [$this->secondary->id],
        ])->assertRedirect();
        app(TenantContext::class)->set($this->admin->school_id);
        $this->assertTrue($this->teacher->fresh()->roles()->where('name', 'Head')->exists());

        $this->put(route('staff.access.update', $this->teacher), [
            'role_id' => $this->roles['Teacher']->id,
            'section_ids' => [$this->primary->id, $this->secondary->id],
            'head_sections_present' => 1,
        ])->assertRedirect();
        app(TenantContext::class)->set($this->admin->school_id);
        $this->assertFalse($this->teacher->fresh()->roles()->where('name', 'Head')->exists());
        $this->assertTrue($this->teacher->fresh()->roles()->where('name', 'Automatic helper')->exists());

        $this->post(route('staff.accounts.store'), [
            'name' => 'Forged Head', 'username' => 'forged.head', 'role_id' => $this->roles['Head']->id,
        ])->assertSessionHasErrors(['role_id' => 'Head is granted by a Head of assignment.']);
        app(TenantContext::class)->set($this->admin->school_id);
        $outsider = $this->user('Outsider', $this->roles['Teacher']);
        $this->actingAs($outsider)->put(route('staff.access.update', $this->teacher), [
            'role_id' => $this->roles['Teacher']->id,
        ])->assertForbidden();
    }

    public function test_existing_principal_role_is_renamed_without_changing_its_permissions_or_assignments(): void
    {
        $headRole = $this->roles['Head'];
        $permissionIds = $headRole->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        $this->teacher->roles()->attach($headRole);
        $headRole->update(['name' => 'Principal']);
        \DB::table('section_user')->where('user_id', $this->teacher->id)->update(['is_head' => false]);

        $migration = require base_path('app/Modules/Academics/Database/Migrations/2026_11_15_000200_separate_head_scope_and_rename_role.php');
        $migration->up();

        $this->assertDatabaseHas('roles', ['id' => $headRole->id, 'name' => 'Head']);
        $this->assertDatabaseMissing('roles', ['school_id' => $this->admin->school_id, 'name' => 'Principal']);
        $this->assertSame($permissionIds, $headRole->fresh()->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all());
        $this->assertTrue($this->teacher->fresh()->roles()->whereKey($headRole->id)->exists());
        $this->assertSame(2, \DB::table('section_user')->where('user_id', $this->teacher->id)->where('is_head', true)->count());
        $this->assertSame(1, app(DefaultRoleCatalog::class)->provision()->filter(fn (Role $role): bool => $role->name === 'Head')->count());
    }

    public function test_repair_is_dry_run_first_idempotent_audited_and_reports_role_without_scope(): void
    {
        $missingRole = $this->user('Scoped without role', $this->roles['Teacher']);
        $missingRole->headSections()->attach($this->primary, ['school_id' => $this->admin->school_id]);
        $roleOnly = $this->user('Role without scope', $this->roles['Head']);

        $this->artisan('staff:repair-head-roles')
            ->expectsOutputToContain('Dry run: 1 account(s) need the Head role.')
            ->expectsOutputToContain('Role without scope')
            ->assertSuccessful();
        app(TenantContext::class)->set($this->admin->school_id);
        $this->assertFalse($missingRole->fresh()->roles()->where('name', 'Head')->exists());

        $this->artisan('staff:repair-head-roles', ['--apply' => true])
            ->expectsOutputToContain('Applied: 1 account(s) need the Head role.')
            ->expectsOutputToContain('Role without scope')
            ->assertSuccessful();
        app(TenantContext::class)->set($this->admin->school_id);
        $this->assertTrue($missingRole->fresh()->roles()->where('name', 'Head')->exists());
        $this->assertTrue($roleOnly->fresh()->roles()->where('name', 'Head')->exists());
        $this->assertFalse($roleOnly->headSections()->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'head_role_repaired', 'subject_id' => $missingRole->id]);

        $this->artisan('staff:repair-head-roles', ['--apply' => true])
            ->expectsOutputToContain('Applied: 0 account(s) need the Head role.')
            ->assertSuccessful();
        app(TenantContext::class)->set($this->admin->school_id);
        $this->assertSame(1, $this->auditCount('head_role_repaired'));
    }

    public function test_promotion_readiness_is_scoped_for_heads_and_school_wide_for_admin(): void
    {
        app(HeadAssignments::class)->setSectionHead($this->primary, $this->teacher, $this->admin);
        $earlyYears = Section::create(['name' => 'Early Years', 'head_title' => 'Head Teacher']);
        $catalog = app(PromotionCatalog::class);

        $this->assertTrue($catalog->waitingForArrangement($this->teacher)->isEmpty());
        $catalog->assertEverySectionArranged($this->teacher);
        $this->assertEqualsCanonicalizing(['Early Years', 'Secondary'], $catalog->waitingForArrangement($this->admin)->pluck('name')->all());

        $this->actingAs($this->teacher)->get(route('academics.promotion.index'))
            ->assertOk()->assertDontSee($earlyYears->name)->assertDontSee('Arrange your classes first');
        $this->actingAs($this->admin)->get(route('academics.promotion.index'))
            ->assertOk()->assertSee('Early Years still needs a saved class order.')
            ->assertSee('Secondary still needs a saved class order.');
    }

    private function user(string $name, Role $role): User
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().'@head.test', 'password' => 'password', 'status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }

    private function auditCount(string $action): int
    {
        return (int) \DB::table('audit_logs')->where('action', $action)->count();
    }
}
