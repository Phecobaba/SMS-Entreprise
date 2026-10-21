<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\CandidateStatusHistory;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\AdmissionPipeline;
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
        app(TenantContext::class)->set($school->id);
        $this->assertSame(0, Student::count());

        $this->post(route('admissions.convert', $candidate), ['class_arm_id' => $arm->id, 'override' => 1, 'override_reason' => 'Original papers will arrive tomorrow'])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(1, Student::where('candidate_id', $candidate->id)->count());
        $event = CandidateStatusHistory::where('candidate_id', $candidate->id)->where('to_status', 'Converted to Student')->firstOrFail();
        $this->assertTrue($event->is_override);
        $this->assertSame($admin->id, $event->changed_by);
        $this->assertSame('Original papers will arrive tomorrow', $event->reason);
        $this->assertSame(8, CandidateStatusHistory::where('candidate_id', $candidate->id)->count());

        $second = $this->acceptedCandidate($pipeline, $admin, 'two@example.test');
        $this->actingAs($admin)->post(route('admissions.checklist', [$second, $item]), ['is_complete' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admissions.convert', $second), ['class_arm_id' => $arm->id])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame(1, Student::where('candidate_id', $second->id)->count());
        $this->assertFalse(CandidateStatusHistory::where('candidate_id', $second->id)->where('to_status', 'Converted to Student')->firstOrFail()->is_override);
        $this->assertSame(8, CandidateStatusHistory::where('candidate_id', $second->id)->count());
    }

    private function acceptedCandidate(AdmissionPipeline $pipeline, User $admin, string $email)
    {
        app(TenantContext::class)->set($admin->school_id);
        $candidate = $pipeline->register(['name' => 'Applicant', 'email' => $email, 'phone' => '08000000000', 'password' => 'long-password-here', 'applied_level_id' => AcademicLevel::value('id')]);
        $invoice = app(PaymentLedger::class)->invoice(['candidate_id' => $candidate->id, 'purpose' => 'Registration', 'amount_kobo' => 10000, 'due_date' => today()->toDateString()], $admin);
        $this->actingAs($admin)->post(route('payments.manual', $invoice))->assertSessionHasNoErrors();
        $this->post(route('admissions.exam-taken', $candidate))->assertSessionHasNoErrors();
        $this->post(route('admissions.exam-outcome', $candidate), ['outcome' => 'Passed'])->assertSessionHasNoErrors();
        $this->post(route('admissions.offer', $candidate), ['response_deadline' => today()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($admin->school_id);
        $candidateUser = User::findOrFail($candidate->user_id);
        $this->actingAs($candidateUser)->post(route('admissions.mine.respond'), ['response' => 'Accepted'])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($admin->school_id);

        return $candidate;
    }
}
