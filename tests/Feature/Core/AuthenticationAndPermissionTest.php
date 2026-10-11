<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_scoped_to_the_school_and_dashboard_requires_a_permission(): void
    {
        $firstSchool = School::create(['name' => 'First School']);
        $secondSchool = School::create(['name' => 'Second School']);
        config()->set('tenancy.default_school_id', $firstSchool->id);
        $context = app(TenantContext::class);

        $context->set($firstSchool->id);
        $user = User::create([
            'name' => 'First User',
            'email' => 'shared@example.test',
            'password' => 'first-password',
        ]);

        $context->set($secondSchool->id);
        User::create([
            'name' => 'Second User',
            'email' => 'shared@example.test',
            'password' => 'second-password',
        ]);
        $context->clear();

        $this->post('/login', ['email' => 'shared@example.test', 'password' => 'second-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'shared@example.test', 'password' => 'first-password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertForbidden();

        $context->set($firstSchool->id);
        $role = Role::create(['name' => 'School Admin']);
        $permission = Permission::create(['name' => 'view_dashboard']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->assertSame(1, DB::table('role_permissions')->count());
        $this->assertSame(1, DB::table('user_roles')->count());
        $this->assertSame($firstSchool->id, DB::table('role_permissions')->value('school_id'));
        $this->assertSame($firstSchool->id, DB::table('user_roles')->value('school_id'));
        $this->assertTrue($user->roles()->exists());
        $this->assertTrue($role->permissions()->exists());
        $this->assertTrue($user->hasPermission('view_dashboard'));
        $context->clear();

        $this->get('/dashboard')->assertOk()->assertSee('Good')->assertSee('First User')->assertSee('Welcome to your Oakfield workspace');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
