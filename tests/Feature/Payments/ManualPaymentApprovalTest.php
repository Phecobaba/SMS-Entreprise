<?php

namespace Tests\Feature\Payments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\ManualPaymentService;
use App\Modules\Payments\Services\ResultPaymentAccess;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualPaymentApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_teller_number_is_race_safe_and_a_rejected_number_can_be_reused(): void
    {
        $f = $this->fixture();
        $service = app(ManualPaymentService::class);
        $first = $service->record($f['invoice'], $this->details(' AB-123 '), $f['bursar'])['submission'];
        $secondInvoice = $this->invoice($f, 'Second charge');

        try {
            ManualPaymentSubmission::create([
                'invoice_id' => $secondInvoice->id, 'bank' => 'Access', 'teller_number' => 'ab 123',
                'active_teller_key' => 'AB123', 'paid_on' => today(), 'amount_kobo' => 4500000,
                'recorded_by' => $f['bursar']->id, 'status' => ManualPaymentSubmission::STATUS_AWAITING,
            ]);
            $this->fail('The database unique key accepted the same active teller twice.');
        } catch (QueryException) {
            $this->assertSame(1, ManualPaymentSubmission::where('active_teller_key', 'AB123')->count());
        }

        try {
            $service->record($secondInvoice, $this->details('ab 123'), $f['bursar']);
            $this->fail('The service accepted a normalised duplicate teller number.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($f['student']->user->name, $exception->errors()['teller_number'][0]);
            $this->assertStringContainsString(today()->format('j M Y'), $exception->errors()['teller_number'][0]);
        }

        $this->assertTrue($service->reject($first, $f['admin'], 'Wrong invoice details.'));
        $replacement = $service->record($secondInvoice, $this->details('ab 123'), $f['bursar'])['submission'];
        $this->assertNull($first->fresh()->active_teller_key);
        $this->assertSame('AB123', $replacement->active_teller_key);
    }

    public function test_awaiting_payment_settles_nothing_stays_locked_and_hides_pay_for_student_and_parent(): void
    {
        $f = $this->fixture(withParent: true);
        $submission = app(ManualPaymentService::class)->record($f['invoice'], $this->details(), $f['bursar'])['submission'];

        $this->assertSame(ManualPaymentSubmission::STATUS_AWAITING, $submission->status);
        $this->assertSame('Unpaid', $f['invoice']->fresh()->status);
        $this->assertDatabaseMissing('payments', ['invoice_id' => $f['invoice']->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual_payment.recorded', 'subject_id' => $submission->id]);
        try {
            app(ResultPaymentAccess::class)->authorizeAttendance($f['student']->id, $f['student']->user);
            $this->fail('Attendance opened before approval.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $card = ResultCard::create([
            'student_id' => $f['student']->id, 'term_id' => $f['term']->id,
            'class_arm_id' => $f['arm']->id, 'compiled_at' => now(),
        ]);
        try {
            app(ResultPaymentAccess::class)->authorize($card, $f['student']->user);
            $this->fail('Results opened before approval.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        foreach ([$f['student']->user, $f['parent']] as $viewer) {
            $this->actingAs($viewer)->get(route('payments.mine.index', ['student' => $f['student']->id]))
                ->assertOk()
                ->assertSee('Awaiting approval')
                ->assertSee("The school has recorded your bank payment and is confirming it. You don't need to pay again.", false)
                ->assertSee('Recorded payment')
                ->assertDontSee('Continue to Paystack')
                ->assertDontSee('GTBank')
                ->assertDontSee('TT-100');
        }
        $this->actingAs($f['parent'])->get(route('dashboard', ['student' => $f['student']->id]))
            ->assertOk()->assertDontSee('Continue to Paystack');
        $this->actingAs($f['student']->user)->post(route('payments.mine.begin', $f['invoice']))
            ->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_approval_settles_the_recorded_amount_once_and_issues_one_receipt(): void
    {
        $f = $this->fixture(withParent: true);
        $service = app(ManualPaymentService::class);
        $submission = $service->record($f['invoice'], $this->details(), $f['bursar'])['submission'];
        $first = $service->approve($submission, $f['admin']);
        $second = $service->approve($submission->fresh(), $f['admin']);

        $this->assertFalse($first['handled']);
        $this->assertTrue($second['handled']);
        $this->assertSame('Paid', $f['invoice']->fresh()->status);
        $this->assertSame(1, Payment::where('invoice_id', $f['invoice']->id)->count());
        $payment = Payment::where('invoice_id', $f['invoice']->id)->firstOrFail();
        $this->assertSame($submission->amount_kobo, $payment->amount_kobo);
        $this->assertNotNull($payment->receipt_number);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['student']->user_id, 'title' => 'Payment confirmed']);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['parent']->id, 'title' => 'Payment confirmed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual_payment.approved', 'subject_id' => $submission->id]);
    }

    public function test_only_an_admin_can_approve_or_reject_and_rejection_settles_nothing(): void
    {
        $f = $this->fixture();
        $submission = app(ManualPaymentService::class)->record($f['invoice'], $this->details(), $f['bursar'])['submission'];

        $this->actingAs($f['bursar'])->post(route('payments.manual-submissions.approve', $submission))->assertForbidden();
        $this->actingAs($f['bursar'])->post(route('payments.manual-submissions.reject', $submission), ['reason' => 'No match'])->assertForbidden();
        $this->actingAs($f['admin'])->post(route('payments.manual-submissions.reject', $submission), ['reason' => 'Teller image is unclear.'])->assertRedirect();

        $this->assertSame('Unpaid', $f['invoice']->fresh()->status);
        $this->assertDatabaseMissing('payments', ['invoice_id' => $f['invoice']->id]);
        $this->assertDatabaseHas('manual_payment_submissions', [
            'id' => $submission->id, 'status' => ManualPaymentSubmission::STATUS_REJECTED,
            'active_teller_key' => null, 'rejection_reason' => 'Teller image is unclear.',
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['bursar']->id, 'title' => 'Bank payment rejected']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual_payment.rejected', 'subject_id' => $submission->id]);
    }

    public function test_approving_a_part_payment_does_not_unlock_the_term_invoice(): void
    {
        $f = $this->fixture();
        $part = Invoice::create([
            'student_id' => $f['student']->id, 'term_id' => $f['term']->id, 'purpose' => 'Tuition part 1',
            'kind' => Invoice::KIND_PART_PAYMENT, 'creation_source' => 'single', 'amount_kobo' => 1000000,
            'due_date' => today(), 'status' => 'Unpaid', 'created_by' => $f['bursar']->id,
        ]);
        $service = app(ManualPaymentService::class);
        $submission = $service->record($part, $this->details('PART-1', 1000000), $f['bursar'])['submission'];
        $service->approve($submission, $f['admin']);

        $this->assertSame('Paid', $part->fresh()->status);
        $this->assertSame('Unpaid', $f['invoice']->fresh()->status);
        $this->expectException(AuthorizationException::class);
        app(ResultPaymentAccess::class)->authorizeAttendance($f['student']->id, $f['student']->user);
    }

    public function test_amount_must_match_the_balance_exactly(): void
    {
        $f = $this->fixture();
        $this->expectException(ValidationException::class);
        app(ManualPaymentService::class)->record($f['invoice'], $this->details('SHORT', 4499999), $f['bursar']);
    }

    public function test_one_step_is_admin_only_defaults_off_notifies_other_admins_and_leaves_waiting_rows_untouched(): void
    {
        $f = $this->fixture(secondAdmin: true);
        $service = app(ManualPaymentService::class);
        $waiting = $service->record($f['invoice'], $this->details('WAIT-1'), $f['bursar'])['submission'];
        $this->assertFalse((bool) (SchoolSetting::first()?->one_step_payment_approval ?? false));

        $this->actingAs($f['bursar'])->put(route('payments.approval-setting.update'), ['enabled' => true])->assertForbidden();
        app(TenantContext::class)->set($f['school']->id);
        $service->setOneStep(true, $f['admin']);
        $this->assertSame(ManualPaymentSubmission::STATUS_AWAITING, $waiting->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $f['otherAdmin']->id, 'title' => 'Manual payment approval changed']);

        $next = $this->invoice($f, 'Next charge');
        $result = $service->record($next, $this->details('AUTO-1'), $f['bursar']);
        $this->assertTrue($result['approved']);
        $this->assertSame(ManualPaymentSubmission::STATUS_APPROVED, $result['submission']->status);
        $this->assertSame('Paid', $next->fresh()->status);
        $this->assertSame(1, Payment::where('invoice_id', $next->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual_payment.auto_approved', 'subject_id' => $result['submission']->id]);

        $service->setOneStep(false, $f['admin']);
        $this->assertSame(ManualPaymentSubmission::STATUS_AWAITING, $waiting->fresh()->status);
    }

    private function fixture(bool $withParent = false, bool $secondAdmin = false): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create([]);

        $admin = User::create(['name' => 'Admin One', 'email' => 'admin-one@example.test', 'password' => 'long-password-here']);
        $bursar = User::create(['name' => 'Bursar', 'email' => 'bursar-manual@example.test', 'password' => 'long-password-here']);
        $learner = User::create(['name' => 'Ada Learner', 'username' => 'OAK/26/001', 'email' => 'ada-pay@example.test', 'password' => 'long-password-here']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([
            Permission::firstOrCreate(['name' => 'manage_payments'])->id,
            Permission::firstOrCreate(['name' => 'approve_payments'])->id,
        ]);
        $bursarRole = Role::create(['name' => 'Bursar']);
        $bursarRole->permissions()->attach(Permission::where('name', 'manage_payments')->firstOrFail());
        $admin->roles()->attach($adminRole);
        $bursar->roles()->attach($bursarRole);

        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE, 'admission_date' => today()]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $data = compact('school', 'admin', 'bursar', 'student', 'term', 'arm');
        $data['invoice'] = $this->invoice($data, 'First Term tuition', Invoice::KIND_TERM_FEE);

        if ($withParent) {
            $parentUser = User::create(['name' => 'Ada Parent', 'email' => 'ada-parent@example.test', 'password' => 'long-password-here']);
            $parent = ParentProfile::create(['user_id' => $parentUser->id]);
            ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id]);
            $data['parent'] = $parentUser;
        }
        if ($secondAdmin) {
            $other = User::create(['name' => 'Admin Two', 'email' => 'admin-two@example.test', 'password' => 'long-password-here']);
            $other->roles()->attach($adminRole);
            $data['otherAdmin'] = $other;
        }

        return $data;
    }

    private function invoice(array $fixture, string $purpose, string $kind = Invoice::KIND_ONE_OFF): Invoice
    {
        return Invoice::create([
            'student_id' => $fixture['student']->id,
            'term_fee_student_id' => $kind === Invoice::KIND_TERM_FEE ? $fixture['student']->id : null,
            'term_id' => $fixture['term']->id, 'purpose' => $purpose, 'kind' => $kind,
            'creation_source' => 'single', 'amount_kobo' => 4500000, 'due_date' => today(),
            'status' => 'Unpaid', 'created_by' => $fixture['bursar']->id,
        ]);
    }

    private function details(string $teller = 'TT-100', int $amount = 4500000): array
    {
        return ['bank' => 'GTBank', 'teller_number' => $teller, 'paid_on' => today()->toDateString(), 'amount_kobo' => $amount];
    }
}
