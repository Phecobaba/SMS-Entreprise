<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\CandidateChecklist;
use App\Modules\Admissions\Models\CandidateStatusHistory;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Admissions\Services\CandidateSlipPdf;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_candidate_registration_route_is_retired_without_creating_an_account(): void
    {
        $school = School::create(['name' => 'Oakfield International Schools']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $this->post(route('admissions.register.store'), [
            'name' => 'Applicant', 'email' => 'candidate@example.test', 'phone' => '08000000000',
            'password' => 'Candidate88', 'password_confirmation' => 'Candidate88',
        ])->assertRedirect(route('applications.create'));
        $this->assertDatabaseMissing('users', ['email' => 'candidate@example.test']);
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_conversion_requires_complete_checklist_or_a_logged_permitted_override(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $role = Role::create(['name' => 'School Admin']);
        $manage = Permission::create(['name' => 'manage_admissions']);
        $role->permissions()->attach($manage);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_payments']));
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'view_all_sections']));
        $admin->roles()->attach($role);
        $section = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $section->heads()->attach($admin, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);

        $pipeline = app(AdmissionPipeline::class);
        $item = $pipeline->addChecklistItem('Documents submitted');
        $candidate = $this->acceptedCandidate($pipeline, $admin, 'one@example.test');

        $this->actingAs($admin)->get(route('admissions.index'))->assertOk()->assertSee('Applicant');
        $this->get(route('admissions.show', $candidate))->assertOk()->assertSee('Convert to student');
        app(TenantContext::class)->set($school->id);
        $candidateUser = User::findOrFail($candidate->user_id);
        $this->actingAs($candidateUser)->get(route('admissions.mine'))->assertOk()->assertSee('Offer Accepted');

        $this->actingAs($admin)->post(route('admissions.convert', $candidate), ['class_arm_id' => $arm->id])
            ->assertSessionHasErrors('checklist');
        app(TenantContext::class)->set($school->id);
        $this->assertSame(0, Student::count());

        $this->post(route('admissions.convert', $candidate), ['class_arm_id' => $arm->id, 'override' => 1, 'override_reason' => 'Original papers will arrive tomorrow'])
            ->assertSessionHasErrors('override');
        app(TenantContext::class)->set($school->id);
        $this->assertSame(0, Student::count());

        app(TenantContext::class)->set($school->id);
        $override = Permission::create(['name' => 'override_admission_checklist']);
        $role->permissions()->attach($override);
        $this->post(route('admissions.convert', $candidate), ['class_arm_id' => $arm->id, 'override' => 1])
            ->assertSessionHasErrors('override_reason');
        $this->post(route('admissions.convert', $candidate), ['class_arm_id' => $arm->id, 'override' => 1, 'override_reason' => 'Too short'])
            ->assertSessionHasErrors('override_reason');
        app(TenantContext::class)->set($school->id);
        $this->assertSame(0, Student::count());

        $this->post(route('admissions.convert', $candidate), ['class_arm_id' => $arm->id, 'override' => 1, 'override_reason' => 'Original papers will arrive tomorrow'])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(1, Student::where('candidate_id', $candidate->id)->count());
        $converted = Student::where('candidate_id', $candidate->id)->firstOrFail();
        $this->assertNotNull($converted->registration_number);
        $this->assertFalse(User::findOrFail($converted->user_id)->must_change_password);
        $event = CandidateStatusHistory::where('candidate_id', $candidate->id)->where('to_status', 'Converted to Student')->firstOrFail();
        $this->assertTrue($event->is_override);
        $this->assertSame($admin->id, $event->changed_by);
        $this->assertSame('Original papers will arrive tomorrow', $event->reason);
        $audit = AuditLog::where('action', 'admission_checklist_overridden')->where('subject_id', $candidate->id)->firstOrFail();
        $this->assertSame('Original papers will arrive tomorrow', data_get($audit->metadata, 'reason'));
        $this->assertSame(1, data_get($audit->metadata, 'outstanding_requirements'));
        $this->assertSame(8, CandidateStatusHistory::where('candidate_id', $candidate->id)->count());

        $second = $this->acceptedCandidate($pipeline, $admin, 'two@example.test');
        $this->actingAs($admin)->post(route('admissions.checklist', [$second, $item]), ['is_complete' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admissions.convert', $second), ['class_arm_id' => $arm->id])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(1, Student::where('candidate_id', $second->id)->count());
        $this->assertFalse(CandidateStatusHistory::where('candidate_id', $second->id)->where('to_status', 'Converted to Student')->firstOrFail()->is_override);
        $this->assertSame(8, CandidateStatusHistory::where('candidate_id', $second->id)->count());
    }

    public function test_application_conversion_keeps_candidate_login_forces_student_change_and_creates_or_reuses_unconfirmed_parent(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'password']);
        $role = Role::create(['name' => 'School Admin']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_admissions']));
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'view_all_sections']));
        $admin->roles()->attach($role);
        $section = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 1']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $item = app(AdmissionPipeline::class)->addChecklistItem('Documents submitted');
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

        $first = $this->applicationCandidate($level, 'first.child', 'guardian@example.test');
        CandidateChecklist::create(['candidate_id' => $first->id, 'checklist_item_id' => $item->id, 'is_complete' => true, 'completed_by' => $admin->id, 'completed_at' => now()]);
        $this->actingAs($admin)->post(route('admissions.convert', $first), ['class_arm_id' => $arm->id])
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        app(TenantContext::class)->set($school->id);
        $student = Student::where('candidate_id', $first->id)->firstOrFail();
        $this->assertTrue($student->user->must_change_password);
        $parent = ParentProfile::with(['user', 'students'])->firstOrFail();
        $this->assertSame('guardian@example.test', $parent->user->email);
        $this->assertNull($parent->user->email_verified_at);
        $this->assertTrue($parent->user->must_change_password);
        $this->assertCount(1, $renderer->slips);

        $second = $this->applicationCandidate($level, 'second.child', 'guardian@example.test');
        CandidateChecklist::create(['candidate_id' => $second->id, 'checklist_item_id' => $item->id, 'is_complete' => true, 'completed_by' => $admin->id, 'completed_at' => now()]);
        $this->actingAs($admin)->post(route('admissions.convert', $second), ['class_arm_id' => $arm->id])
            ->assertRedirect(route('admissions.show', $second));
        app(TenantContext::class)->set($school->id);
        $this->assertSame(1, ParentProfile::count());
        $this->assertSame(2, $parent->fresh()->students()->count());
    }

    private function acceptedCandidate(AdmissionPipeline $pipeline, User $admin, string $email)
    {
        app(TenantContext::class)->set($admin->school_id);
        $candidate = $pipeline->register(['name' => 'Applicant', 'email' => $email, 'phone' => '08000000000', 'password' => 'long-password-here', 'applied_level_id' => AcademicLevel::value('id')]);
        $invoice = app(PaymentLedger::class)->invoice(['candidate_id' => $candidate->id, 'purpose' => 'Registration', 'amount_kobo' => 10000, 'due_date' => today()->toDateString()], $admin);
        app(PaymentLedger::class)->settleManualSubmission($invoice, $invoice->amount_kobo, $admin);
        $this->actingAs($admin);
        $this->post(route('admissions.exam-taken', $candidate))->assertSessionHasNoErrors();
        $this->post(route('admissions.exam-outcome', $candidate), ['outcome' => 'Passed'])->assertSessionHasNoErrors();
        $this->post(route('admissions.offer', $candidate), ['response_deadline' => today()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($admin->school_id);
        $candidateUser = User::findOrFail($candidate->user_id);
        $this->actingAs($candidateUser)->post(route('admissions.mine.respond'), ['response' => 'Accepted'])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($admin->school_id);

        return $candidate;
    }

    private function applicationCandidate(AcademicLevel $level, string $username, string $guardianEmail): Candidate
    {
        $application = Application::create([
            'public_reference' => 'APP-'.strtoupper(str_replace('.', '-', $username)),
            'child_first_name' => ucfirst(strtok($username, '.')), 'child_surname' => 'Child',
            'date_of_birth' => '2017-05-10', 'gender' => 'Female', 'applied_level_id' => $level->id,
            'guardian_name' => 'Guardian Name', 'guardian_relationship' => 'Mother', 'guardian_phone' => '08000000000',
            'guardian_email' => $guardianEmail, 'guardian_address' => 'Lagos', 'username' => $username,
            'status' => 'Registered', 'fee_amount_kobo' => 0, 'currency' => 'NGN',
            'access_token_hash' => hash('sha256', $username), 'consented_at' => now(), 'consent_version' => 'test',
            'expires_at' => now()->addDays(7), 'registered_at' => now(),
        ]);
        $user = User::create(['name' => $application->child_first_name.' Child', 'username' => $username, 'password' => 'candidate-password', 'must_change_password' => false]);

        return Candidate::create([
            'user_id' => $user->id, 'application_id' => $application->id, 'applied_level_id' => $level->id,
            'name' => $user->name, 'phone' => $application->guardian_phone, 'status' => 'Offer Accepted',
        ]);
    }
}
