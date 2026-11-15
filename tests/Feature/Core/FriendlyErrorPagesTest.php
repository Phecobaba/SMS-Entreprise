<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FriendlyErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_post_only_get_routes_render_the_friendly_404_page(): void
    {
        $this->tenant();
        config()->set('app.debug', false);

        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('We could not find that page.')
            ->assertDontSee('Stack trace');
        $this->get('/payments/gateway/test')
            ->assertNotFound()
            ->assertSee('We could not find that page.')
            ->assertDontSee('MethodNotAllowedHttpException');
    }

    public function test_get_logout_never_signs_out_the_current_user(): void
    {
        $school = $this->tenant();
        $user = User::create([
            'name' => 'Signed In User', 'email' => 'signed-in@example.test', 'password' => 'long-password-here',
        ]);

        $this->get('/logout')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/logout')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($school->id, $user->school_id);
    }

    public function test_session_expiry_is_friendly_for_guests_and_signed_in_users(): void
    {
        $school = $this->tenant();
        Route::middleware('web')->get('/_test/session-expired', function (): never {
            throw new TokenMismatchException;
        });

        $this->get('/_test/session-expired')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session expired. Please sign in again.');

        app(TenantContext::class)->set($school->id);
        $permission = Permission::create(['name' => 'view_dashboard']);
        $role = Role::create(['name' => 'Teacher']);
        $role->permissions()->attach($permission);
        $user = User::create([
            'name' => 'Teacher', 'email' => 'session-teacher@example.test', 'password' => 'long-password-here',
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/_test/session-expired')
            ->assertStatus(419)
            ->assertSee('Your session expired. Please sign in again.')
            ->assertDontSee('TokenMismatchException');
    }

    private function tenant(): School
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        return $school;
    }
}
