<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Mail\ApplicationExamSessionMail;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ExamSession;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AppNavigation;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ApplicationExamSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_paid_applications_are_assigned_email_is_once_and_used_session_cannot_be_deleted(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $session = $this->examSession($f['admin'], $f['primary']);
        $unpaid = $this->application($f['primaryLevel'], 'unpaid.child');
        $paid = $this->application($f['primaryLevel'], 'paid.child', ['status' => 'Registered']);

        $this->actingAs($f['admin'])->post(route('admissions.applications.assign-session'), [
            'applications' => [$unpaid->id], 'exam_session_id' => $session->id,
        ])->assertSessionHasErrors('applications');
        $this->restore();
        $this->assertNull($unpaid->fresh()->exam_session_id);

        $this->actingAs($f['admin'])->post(route('admissions.applications.assign-session'), [
            'applications' => [$paid->id], 'exam_session_id' => $session->id,
        ])->assertRedirect(route('admissions.applications.index', ['tab' => 'awaiting_date']));
        $this->restore();
        $this->assertSame($session->id, $paid->fresh()->exam_session_id);
        Mail::assertSent(ApplicationExamSessionMail::class, 1);

        $this->actingAs($f['admin'])->post(route('admissions.applications.assign-session'), [
            'applications' => [$paid->id], 'exam_session_id' => $session->id,
        ])->assertRedirect();
        $this->restore();
        Mail::assertSent(ApplicationExamSessionMail::class, 1);

        $otherSession = $this->examSession($f['admin'], $f['primary'], ['name' => 'Later sitting']);
        $this->actingAs($f['admin'])->put(route('admissions.applications.session.update', $paid), [
            'exam_session_id' => $otherSession->id,
        ])->assertRedirect();
        $this->restore();
        $this->assertSame($otherSession->id, $paid->fresh()->exam_session_id);
        Mail::assertSent(ApplicationExamSessionMail::class, 2);

        $this->actingAs($f['admin'])->delete(route('admissions.applications.session.destroy', $paid))->assertRedirect();
        $this->restore();
        $this->assertNull($paid->fresh()->exam_session_id);
        Mail::assertSent(ApplicationExamSessionMail::class, 2);

        $paid->update(['exam_session_id' => $session->id]);
        $this->actingAs($f['admin'])->delete(route('admissions.sessions.destroy', $session))
            ->assertSessionHasErrors('session');
        $this->restore();
        $this->assertModelExists($session);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_exam_session_assigned', 'subject_id' => $paid->id]);
    }

    public function test_session_edit_emails_assigned_applicants_only_when_confirmed(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $session = $this->examSession($f['admin'], $f['primary']);
        $paid = $this->application($f['primaryLevel'], 'paid.child', ['status' => 'Registered', 'exam_session_id' => $session->id]);

        $payload = ['name' => 'Primary sitting', 'starts_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d H:i:s'), 'venue_note' => 'Main hall', 'is_in_person' => '1', 'section_id' => $f['primary']->id];
        $this->actingAs($f['admin'])->put(route('admissions.sessions.update', $session), [...$payload, 'email_applicants' => '0'])->assertRedirect();
        $this->restore();
        Mail::assertNothingSent();

        $this->actingAs($f['admin'])->put(route('admissions.sessions.update', $session), [
            ...$payload, 'venue_note' => 'Library hall', 'email_applicants' => '1',
        ])->assertRedirect();
        $this->restore();
        Mail::assertSent(ApplicationExamSessionMail::class, 1);
        $this->assertNotNull($paid->fresh()->exam_notified_at);
    }

    public function test_head_is_limited_to_own_section_and_unpermitted_user_is_forbidden(): void
    {
        $f = $this->fixture();
        Mail::fake();
        $primarySession = $this->examSession($f['admin'], $f['primary'], ['name' => 'Primary sitting']);
        $secondarySession = $this->examSession($f['admin'], $f['secondary'], ['name' => 'Secondary sitting']);
        $own = $this->application($f['primaryLevel'], 'own.child', ['status' => 'Registered']);
        $other = $this->application($f['secondaryLevel'], 'other.child', ['status' => 'Registered']);

        $this->actingAs($f['head'])->get(route('admissions.applications.index'))
            ->assertOk()->assertSee('own.child')->assertDontSee('other.child');
        $this->actingAs($f['head'])->get(route('admissions.applications.show', $other))->assertNotFound();
        $this->actingAs($f['head'])->get(route('admissions.sessions.edit', $secondarySession))->assertNotFound();
        $this->actingAs($f['head'])->post(route('admissions.applications.assign-session'), [
            'applications' => [$other->id], 'exam_session_id' => $secondarySession->id,
        ])->assertNotFound();
        $this->actingAs($f['head'])->post(route('admissions.applications.assign-session'), [
            'applications' => [$own->id], 'exam_session_id' => $primarySession->id,
        ])->assertRedirect();
        $this->actingAs($f['other'])->get(route('admissions.sessions.index'))->assertForbidden();
    }

    public function test_status_discloses_session_only_to_cookie_owner_or_signed_link(): void
    {
        $f = $this->fixture();
        $session = $this->examSession($f['admin'], $f['primary'], ['name' => 'Saturday sitting', 'venue_note' => 'Blue Hall']);
        $application = $this->application($f['primaryLevel'], 'status.child', [
            'status' => 'Registered', 'exam_session_id' => $session->id, 'access_token_hash' => hash('sha256', 'owner-token'),
        ]);

        $this->withCookie(config('admissions.cookie'), 'owner-token')->get(route('applications.status'))
            ->assertOk()->assertSee('Saturday sitting')->assertSee('Blue Hall');
        $signed = URL::temporarySignedRoute('applications.status.signed', now()->addHour(), ['application' => $application->public_reference]);
        $this->get($signed)->assertOk()->assertSee('Saturday sitting')->assertSee('Blue Hall');
        $this->withCookie(config('admissions.cookie'), 'wrong')->get(route('applications.status'))
            ->assertRedirect(route('applications.create'))->assertDontSee('Saturday sitting');
    }

    public function test_navigation_shows_admissions_children_only_for_matching_permissions(): void
    {
        $f = $this->fixture();
        $admin = collect(app(AppNavigation::class)->items($f['admin']))->firstWhere('label', 'Admissions');
        $this->assertSame(['Applications', 'Exam sessions', 'Admission settings', 'Candidates', 'Entrance exams'], collect($admin['children'])->pluck('label')->all());

        $head = collect(app(AppNavigation::class)->items($f['head']))->firstWhere('label', 'Admissions');
        $this->assertSame(['Applications', 'Exam sessions'], collect($head['children'])->pluck('label')->all());
        $this->assertNull(collect(app(AppNavigation::class)->items($f['other']))->firstWhere('label', 'Admissions'));
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create(['admissions_open' => true, 'application_fee_enabled' => false, 'application_fee_kobo' => 0]);
        $primary = Section::create(['name' => 'Primary', 'position' => 1, 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary', 'position' => 2, 'head_title' => 'Principal']);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary 1', 'position' => 1]);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'JS 1', 'position' => 2]);
        foreach (['manage_admissions', 'manage_exam_sessions', 'manage_cbt', 'view_all_sections'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->sync(Permission::whereIn('name', ['manage_admissions', 'manage_exam_sessions', 'manage_cbt', 'view_all_sections'])->pluck('id'));
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->sync([Permission::where('name', 'manage_exam_sessions')->value('id')]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.one', 'password' => 'password']);
        $admin->roles()->attach($adminRole);
        $head = User::create(['name' => 'Head', 'username' => 'head.one', 'password' => 'password']);
        $head->roles()->attach($headRole);
        $head->sections()->attach($primary, ['school_id' => $school->id]);
        $other = User::create(['name' => 'Other', 'username' => 'other.one', 'password' => 'password']);

        return compact('school', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel', 'admin', 'head', 'other');
    }

    private function examSession(User $actor, Section $section, array $overrides = []): ExamSession
    {
        return ExamSession::create(array_merge([
            'name' => 'Entrance sitting', 'starts_at' => now()->addDays(2)->setTime(9, 0),
            'venue_note' => 'Assembly hall', 'is_in_person' => true,
            'section_id' => $section->id, 'created_by' => $actor->id,
        ], $overrides));
    }

    private function application(AcademicLevel $level, string $username, array $overrides = []): Application
    {
        return Application::create(array_merge([
            'public_reference' => 'APP-'.strtoupper(str_replace('.', '-', $username)),
            'child_first_name' => 'Ada', 'child_surname' => 'Okafor', 'date_of_birth' => '2017-05-10',
            'gender' => 'Female', 'applied_level_id' => $level->id, 'guardian_name' => 'Parent Okafor',
            'guardian_relationship' => 'Mother', 'guardian_phone' => '08000000000',
            'guardian_email' => $username.'@example.test', 'guardian_address' => 'Lagos',
            'username' => $username, 'active_username_key' => $username, 'status' => 'Awaiting payment',
            'fee_amount_kobo' => 50000, 'currency' => 'NGN', 'access_token_hash' => hash('sha256', $username),
            'consented_at' => now(), 'consent_version' => 'test', 'expires_at' => now()->addDays(7),
        ], $overrides));
    }

    private function restore(): void
    {
        app(TenantContext::class)->set((int) config('tenancy.default_school_id'));
    }
}
