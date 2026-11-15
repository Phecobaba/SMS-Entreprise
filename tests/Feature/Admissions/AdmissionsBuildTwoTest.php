<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\AdmissionChecklistItem;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\ExamSession;
use App\Modules\Admissions\Services\CandidateAccount;
use App\Modules\Admissions\Services\CandidateSlipPdf;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CurrentSchoolProfile;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdmissionsBuildTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_creation_is_scoped_idempotent_secret_safe_and_rolls_back_when_pdf_fails(): void
    {
        $f = $this->fixture();
        $online = $this->application($f['primaryLevel'], $f['session'], 'online.child');
        $checklistItem = AdmissionChecklistItem::create(['label' => 'Birth certificate']);
        $inPerson = $this->application($f['earlyLevel'], $f['session'], 'inperson.child');
        $clash = $this->application($f['primaryLevel'], $f['session'], 'used.child');
        User::create(['name' => 'Existing', 'username' => 'used.child', 'password' => 'not-the-generated-password']);
        $renderer = new class extends CandidateSlipPdf
        {
            public array $slips = [];

            public function render(array $slips): string
            {
                $this->slips = $slips;

                return '%PDF-1.4 test';
            }
        };
        $this->app->instance(CandidateSlipPdf::class, $renderer);

        $response = $this->actingAs($f['admin'])->post(route('admissions.sessions.accounts.store', $f['session']), [
            'applications' => [$online->id, $inPerson->id, $clash->id],
        ]);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->restore();
        $this->assertCount(1, $renderer->slips);
        $secret = $renderer->slips[0]['temporary_password'];
        $candidate = Candidate::with('user')->firstOrFail();
        $this->assertSame($online->id, $candidate->application_id);
        $this->assertDatabaseHas('candidate_checklist', [
            'candidate_id' => $candidate->id,
            'checklist_item_id' => $checklistItem->id,
        ]);
        $this->assertNull($candidate->user->email);
        $this->assertFalse($candidate->user->must_change_password);
        $this->assertTrue(Hash::check($secret, $candidate->user->password));
        $this->assertDatabaseMissing('users', ['password' => $secret]);
        $this->assertStringNotContainsString($secret, AuditLog::get()->toJson());
        $this->assertSame('Assessed in person', app(CandidateAccount::class)->preview($f['session'], $f['admin'])['skipped']->firstWhere('application.id', $inPerson->id)['reason']);

        $hash = $candidate->user->password;
        app(CandidateAccount::class)->createForSession($f['session'], [$online->id], $f['admin']);
        $this->assertSame(1, Candidate::count());
        $this->assertSame($hash, $candidate->user->fresh()->password);

        $another = $this->application($f['primaryLevel'], $f['session'], 'rollback.child');
        $this->app->instance(CandidateSlipPdf::class, new class extends CandidateSlipPdf
        {
            public function render(array $slips): string
            {
                throw new RuntimeException('PDF renderer failed');
            }
        });
        try {
            app(CandidateAccount::class)->createForSession($f['session'], [$another->id], $f['admin']);
            $this->fail('The renderer failure should escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('PDF renderer failed', $exception->getMessage());
        }
        $this->assertFalse(Candidate::where('application_id', $another->id)->exists());
        $this->assertFalse(User::where('username', 'rollback.child')->exists());
        $this->actingAs($f['head'])->post(route('admissions.sessions.accounts.store', $f['session']), ['applications' => [$another->id]])->assertForbidden();
    }

    public function test_reset_changes_candidate_password_revokes_sessions_and_never_forces_candidate_change(): void
    {
        $f = $this->fixture();
        $application = $this->application($f['primaryLevel'], $f['session'], 'reset.child');
        $renderer = new class extends CandidateSlipPdf
        {
            public array $slips = [];

            public function render(array $slips): string
            {
                $this->slips = $slips;

                return '%PDF-1.4 test';
            }
        };
        $this->app->instance(CandidateSlipPdf::class, $renderer);
        app(CandidateAccount::class)->createForSession($f['session'], [$application->id], $f['admin']);
        $candidate = Candidate::with('user')->firstOrFail();
        $before = $candidate->user->password;
        DB::table('sessions')->insert([
            'id' => 'candidate-session', 'user_id' => $candidate->user_id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($f['admin'])->post(route('admissions.sessions.accounts.reset', $f['session']), [
            'candidates' => [$candidate->id],
        ])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->restore();
        $candidate->user->refresh();
        $this->assertNotSame($before, $candidate->user->password);
        $this->assertFalse($candidate->user->must_change_password);
        $this->assertDatabaseMissing('sessions', ['id' => 'candidate-session']);
        $this->assertTrue(Hash::check($renderer->slips[0]['temporary_password'], $candidate->user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'candidate_passwords_reset', 'subject_id' => $f['session']->id]);
        $this->actingAs($f['head'])->post(route('admissions.sessions.accounts.reset', $f['session']), ['candidates' => [$candidate->id]])->assertForbidden();
    }

    public function test_head_records_only_own_section_outcome_and_website_settings_are_admin_only(): void
    {
        $f = $this->fixture();
        $own = $this->application($f['primaryLevel'], $f['session'], 'own.outcome');
        $otherSession = ExamSession::create(['name' => 'Secondary', 'starts_at' => now(), 'venue_note' => 'Hall', 'is_in_person' => true, 'section_id' => $f['secondary']->id, 'created_by' => $f['admin']->id]);
        $other = $this->application($f['secondaryLevel'], $otherSession, 'other.outcome');

        $this->actingAs($f['head'])->put(route('admissions.applications.outcome.update', $own), [
            'assessment_outcome' => 'Recommended', 'assessment_note' => 'Ready for the next step.',
        ])->assertRedirect();
        $this->restore();
        $this->assertSame('Recommended', $own->fresh()->assessment_outcome);
        $this->actingAs($f['head'])->put(route('admissions.applications.outcome.update', $other), [
            'assessment_outcome' => 'Not recommended',
        ])->assertNotFound();
        $this->actingAs($f['admin'])->put(route('admissions.applications.outcome.update', $other), [
            'assessment_outcome' => 'Undecided',
        ])->assertRedirect();
        $this->restore();
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_assessment_recorded', 'subject_id' => $own->id]);

        $this->actingAs($f['head'])->get(route('website-settings.edit'))->assertForbidden();
        $this->actingAs($f['admin'])->put(route('website-settings.update'), [
            'whatsapp_number' => '08000000000', 'opening_hours' => 'Monday to Friday, 8am to 4pm',
            'admissions_key_dates' => 'Entrance assessment in August.', 'admissions_reopen_text' => 'Admissions reopen in January.',
            'privacy_contact_email' => 'privacy@example.test', 'privacy_last_updated_at' => '2026-11-14',
            'non_admitted_retention_text' => 'Unsuccessful application records are retained for one year.',
        ])->assertRedirect(route('website-settings.edit'));
        $this->restore();
        $profile = app(CurrentSchoolProfile::class)->landingPage();
        $this->assertSame('Monday to Friday, 8am to 4pm', $profile['opening_hours']);
        $this->assertSame('privacy@example.test', $profile['privacy_contact_email']);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create(['admissions_open' => true]);
        $primary = Section::create(['name' => 'Primary', 'position' => 1, 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary', 'position' => 2, 'head_title' => 'Principal']);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary 1']);
        $earlyLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Nursery 1', 'assessed_in_person' => true]);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'JS 1']);
        foreach (['manage_admissions', 'manage_exam_sessions', 'view_all_sections', 'manage_school_profile'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->sync(Permission::whereIn('name', ['manage_admissions', 'manage_exam_sessions', 'view_all_sections', 'manage_school_profile'])->pluck('id'));
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->sync([Permission::where('name', 'manage_exam_sessions')->value('id')]);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.one', 'password' => 'password']);
        $admin->roles()->attach($adminRole);
        $head = User::create(['name' => 'Head', 'username' => 'head.one', 'password' => 'password']);
        $head->roles()->attach($headRole);
        $head->sections()->attach($primary, ['school_id' => $school->id]);
        $session = ExamSession::create(['name' => 'Entrance day', 'starts_at' => now(), 'venue_note' => 'Main hall', 'is_in_person' => true, 'section_id' => $primary->id, 'created_by' => $admin->id]);

        return compact('school', 'primary', 'secondary', 'primaryLevel', 'earlyLevel', 'secondaryLevel', 'admin', 'head', 'session');
    }

    private function application(AcademicLevel $level, ExamSession $session, string $username): Application
    {
        return Application::create([
            'public_reference' => 'APP-'.strtoupper(str_replace('.', '-', $username)),
            'child_first_name' => 'Ada', 'child_surname' => ucfirst(strtok($username, '.')), 'date_of_birth' => '2017-05-10',
            'gender' => 'Female', 'applied_level_id' => $level->id, 'guardian_name' => 'Parent Ada',
            'guardian_relationship' => 'Mother', 'guardian_phone' => '08000000000',
            'guardian_email' => $username.'@example.test', 'guardian_address' => 'Lagos',
            'username' => $username, 'active_username_key' => $username, 'status' => 'Registered',
            'fee_amount_kobo' => 0, 'currency' => 'NGN', 'access_token_hash' => hash('sha256', $username),
            'consented_at' => now(), 'consent_version' => 'test', 'expires_at' => now()->addDays(7),
            'registered_at' => now(), 'exam_session_id' => $session->id,
        ]);
    }

    private function restore(): void
    {
        app(TenantContext::class)->set((int) config('tenancy.default_school_id'));
    }
}
