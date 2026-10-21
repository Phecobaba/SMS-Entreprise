<?php

namespace Tests\Feature\Notifications;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Services\InAppNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_urls_accept_only_safe_same_site_paths(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $user = User::create(['name' => 'Parent', 'email' => 'url-parent@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $service = app(InAppNotifications::class);

        $service->send([$user->id], 'Fees', 'A fee is due.', '/my-fees?student=3');
        $this->assertSame('/my-fees?student=3', Notification::firstOrFail()->url);

        foreach (['https://x.com', '//x.com', 'javascript:alert(1)', '\\x'] as $url) {
            try {
                $service->send([$user->id], 'Unsafe', 'Must fail.', $url);
                $this->fail('Unsafe URL was accepted: '.$url);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('url', $exception->errors());
            }
        }

        $service->send([$user->id], 'Text only', 'No destination.');
        $this->assertNull(Notification::latest('id')->firstOrFail()->url);
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()->assertSee('href="/my-fees?student=3"', false);
    }
}
