<?php

namespace Tests\Feature\Payments;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\FeeItem;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\FeeInvoicingService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Payments\Services\ResultPaymentAccess;
use App\Modules\Portals\Services\PortalContext;
use App\Modules\Portals\Services\PortalOverview;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeeInvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_schedule_requires_permission_and_stores_and_renders_integer_kobo(): void
    {
        $data = $this->context();
        $this->actingAs($data['studentUser'])->get(route('fees.schedule.index'))->assertForbidden();
        $this->actingAs($data['studentUser'])->post(route('fees.items.store'), ['name' => 'Tuition'])->assertForbidden();
        app(TenantContext::class)->set($data['school']->id);

        $item = FeeItem::create(['name' => 'Tuition']);
        $this->actingAs($data['bursar'])->post(route('fees.schedule-lines.store'), [
            'term_id' => $data['term']->id, 'scope_type' => 'level', 'scope_id' => $data['level']->id,
            'fee_item_id' => $item->id, 'amount_naira' => '12,500.50', 'is_required' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        app(TenantContext::class)->set($data['school']->id);
        $this->assertSame(1250050, FeeScheduleLine::firstOrFail()->amount_kobo);
        $this->actingAs($data['bursar'])->get(route('fees.schedule.index', ['term' => $data['term']->id]))
            ->assertOk()->assertSee('₦12,500.50');
    }

    public function test_bulk_preview_matches_apply_is_idempotent_and_invoice_total_equals_snapshot_lines(): void
    {
        $data = $this->context();
        $secondUser = User::create(['name' => 'No schedule', 'email' => 'no-schedule@example.test', 'password' => 'long-password-here']);
        $otherLevel = AcademicLevel::create(['name' => 'Other']);
        $otherClass = SchoolClass::create(['academic_level_id' => $otherLevel->id, 'name' => 'Other 1']);
        $otherArm = ClassArm::create(['class_id' => $otherClass->id, 'name' => 'A']);
        Student::create(['user_id' => $secondUser->id, 'class_arm_id' => $otherArm->id, 'admission_date' => today()]);
        $noClassUser = User::create(['name' => 'No class', 'email' => 'no-class@example.test', 'password' => 'long-password-here']);
        $noClassStudent = Student::create(['user_id' => $noClassUser->id, 'class_arm_id' => $otherArm->id, 'admission_date' => today()]);
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('students')->where('id', $noClassStudent->id)->update(['class_arm_id' => 999999]);
        $item = FeeItem::create(['name' => 'Tuition']);
        $line = FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id,
            'fee_item_id' => $item->id, 'amount_kobo' => 5000000, 'is_required' => true]);
        $service = app(FeeInvoicingService::class);
        $preview = $service->preview($data['term']->id, 'school', null);
        $this->assertSame(3, $preview['students_in_scope']);
        $this->assertSame(1, $preview['will_create']);
        $this->assertSame(1, $preview['no_schedule']);
        $this->assertSame(1, $preview['no_class']);
        $report = $service->apply($data['term']->id, 'school', null, '2026-11-01', $data['bursar']);
        $this->assertSame($preview['will_create'], $report['created']);
        $this->assertSame($preview['skipped'], $report['skipped']);
        $invoice = Invoice::with('lines')->where('kind', Invoice::KIND_TERM_FEE)->firstOrFail();
        $this->assertSame($invoice->amount_kobo, $invoice->lines->sum('amount_kobo'));
        $line->update(['amount_kobo' => 7000000]);
        $this->assertSame(5000000, $invoice->fresh()->amount_kobo);
        $this->assertSame(5000000, $invoice->lines()->sum('amount_kobo'));
        $again = $service->apply($data['term']->id, 'school', null, '2026-11-01', $data['bursar']);
        $this->assertSame(0, $again['created']);
        $this->assertSame(1, Invoice::where('kind', Invoice::KIND_TERM_FEE)->count());
    }

    public function test_parent_fees_due_pill_uses_term_fee_arrears_and_ignores_one_offs(): void
    {
        $data = $this->context();
        $parent = User::create(['name' => 'Parent', 'email' => 'fees-parent@example.test', 'password' => 'long-password-here']);
        $profile = ParentProfile::create(['user_id' => $parent->id]);
        ParentStudent::create(['parent_id' => $profile->id, 'student_id' => $data['student']->id]);
        $oneOffUser = User::create(['name' => 'One-off child', 'email' => 'one-off-child@example.test', 'password' => 'long-password-here']);
        $oneOffStudent = Student::create(['user_id' => $oneOffUser->id, 'class_arm_id' => $data['arm']->id, 'admission_date' => today()]);
        ParentStudent::create(['parent_id' => $profile->id, 'student_id' => $oneOffStudent->id]);
        $earlier = AcademicTerm::create(['session_id' => $data['session']->id, 'name' => 'Earlier Term', 'start_date' => '2026-01-01', 'end_date' => '2026-04-30']);
        Invoice::create(['student_id' => $data['student']->id, 'term_id' => $earlier->id, 'purpose' => 'Tuition',
            'amount_kobo' => 3000000, 'due_date' => '2026-04-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);
        Invoice::create(['student_id' => $oneOffStudent->id, 'term_id' => $data['term']->id, 'purpose' => 'Trip', 'kind' => Invoice::KIND_ONE_OFF,
            'amount_kobo' => 900000, 'due_date' => '2026-11-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);

        $response = $this->actingAs($parent)->get(route('dashboard', ['student' => $data['student']->id]));
        $response->assertOk()->assertSee('Fees due')->assertViewHas('overview', function (array $overview) use ($data, $oneOffStudent): bool {
            $wards = collect($overview['wards'])->keyBy('id');

            return $wards[$data['student']->id]['fees_due'] === true
                && $wards[$oneOffStudent->id]['fees_due'] === false;
        });
        $this->assertSame(1, substr_count($response->getContent(), 'Fees due'));
        app(TenantContext::class)->set($data['school']->id);
        $ledger = app(PaymentLedger::class);
        $this->assertSame($ledger->termFeeOutstandingKobo($data['student']->id) > 0, $ledger->wardFeesDue($data['student']->id));
        $this->assertSame($ledger->termFeeOutstandingKobo($oneOffStudent->id) > 0, $ledger->wardFeesDue($oneOffStudent->id));
    }

    public function test_most_specific_duplicate_wins_while_different_matching_items_add(): void
    {
        $data = $this->context();
        $tuition = FeeItem::create(['name' => 'Tuition']);
        $levy = FeeItem::create(['name' => 'Development levy']);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id, 'fee_item_id' => $tuition->id, 'amount_kobo' => 1000000, 'is_required' => true]);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'class', 'class_id' => $data['class']->id, 'fee_item_id' => $tuition->id, 'amount_kobo' => 2000000, 'is_required' => true]);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id, 'fee_item_id' => $levy->id, 'amount_kobo' => 500000, 'is_required' => true]);
        $lines = app(FeeInvoicingService::class)->linesForStudent($data['student'], $data['term']->id);
        $this->assertCount(2, $lines);
        $this->assertSame(2500000, $lines->sum('amount_kobo'));
    }

    public function test_one_off_invoice_neither_satisfies_nor_blocks_term_result_gate(): void
    {
        $data = $this->context();
        $card = new ResultCard(['student_id' => $data['student']->id, 'term_id' => $data['term']->id]);
        Invoice::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id, 'purpose' => 'Trip', 'kind' => Invoice::KIND_ONE_OFF,
            'creation_source' => 'single', 'amount_kobo' => 100000, 'due_date' => '2026-11-01', 'status' => 'Paid', 'created_by' => $data['bursar']->id]);
        $this->expectException(AuthorizationException::class);
        app(ResultPaymentAccess::class)->authorize($card, $data['studentUser']);
    }

    public function test_paid_term_invoice_allows_results_even_with_an_unpaid_one_off(): void
    {
        $data = $this->context();
        $termInvoice = Invoice::create(['student_id' => $data['student']->id, 'term_fee_student_id' => $data['student']->id, 'term_id' => $data['term']->id,
            'purpose' => 'Tuition', 'kind' => Invoice::KIND_TERM_FEE, 'creation_source' => 'single', 'amount_kobo' => 5000000,
            'due_date' => '2026-11-01', 'status' => 'Paid', 'created_by' => $data['bursar']->id]);
        Payment::create(['invoice_id' => $termInvoice->id, 'amount_kobo' => 5000000, 'method' => 'Manual', 'confirmed_by' => $data['bursar']->id,
            'paid_at' => now(), 'receipt_number' => 'TEST-1']);
        Invoice::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id, 'purpose' => 'Trip', 'kind' => Invoice::KIND_ONE_OFF,
            'creation_source' => 'single', 'amount_kobo' => 100000, 'due_date' => '2026-11-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);
        app(ResultPaymentAccess::class)->authorize(new ResultCard(['student_id' => $data['student']->id, 'term_id' => $data['term']->id]), $data['studentUser']);
        $this->assertTrue(true);
    }

    public function test_earlier_term_arrears_are_shown_without_locking_paid_current_results_and_one_offs_stay_out(): void
    {
        $data = $this->context();
        $earlier = AcademicTerm::create(['session_id' => $data['session']->id, 'name' => 'Earlier Term', 'start_date' => '2026-01-01', 'end_date' => '2026-04-30']);
        $earlierInvoice = Invoice::create(['student_id' => $data['student']->id, 'term_id' => $earlier->id, 'purpose' => 'Tuition',
            'amount_kobo' => 3000000, 'due_date' => '2026-04-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);
        $currentInvoice = Invoice::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id, 'purpose' => 'Tuition',
            'amount_kobo' => 5000000, 'due_date' => '2026-11-01', 'status' => 'Paid', 'created_by' => $data['bursar']->id]);
        Payment::create(['invoice_id' => $currentInvoice->id, 'amount_kobo' => 5000000, 'method' => 'Manual',
            'confirmed_by' => $data['bursar']->id, 'paid_at' => now(), 'receipt_number' => 'CURRENT-PAID']);
        Invoice::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id, 'purpose' => 'Trip', 'kind' => Invoice::KIND_ONE_OFF,
            'amount_kobo' => 2000000, 'due_date' => '2026-11-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);

        $summary = app(PaymentLedger::class)->portalSummary($data['student']->id, $data['studentUser']);
        $this->assertSame(3000000, $summary['outstanding_kobo']);

        $card = ResultCard::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id,
            'class_arm_id' => $data['arm']->id, 'compiled_at' => now()]);
        $this->actingAs($data['studentUser'])->get(route('results.mine.show', $card))
            ->assertOk()->assertViewHas('locked', false);

        app(TenantContext::class)->set($data['school']->id);
        Payment::create(['invoice_id' => $earlierInvoice->id, 'amount_kobo' => 1000000, 'method' => 'Manual',
            'confirmed_by' => $data['bursar']->id, 'paid_at' => now(), 'receipt_number' => 'EARLIER-PART']);
        $this->assertSame(2000000, app(PaymentLedger::class)->portalSummary($data['student']->id, $data['studentUser'])['outstanding_kobo']);
    }

    public function test_portal_outstanding_sums_each_term_fee_invoice_balance_once(): void
    {
        $data = $this->context();
        $earlier = AcademicTerm::create(['session_id' => $data['session']->id, 'name' => 'Earlier Term', 'start_date' => '2026-01-01', 'end_date' => '2026-04-30']);
        $first = Invoice::create(['student_id' => $data['student']->id, 'term_id' => $earlier->id, 'purpose' => 'Tuition',
            'amount_kobo' => 5000000, 'due_date' => '2026-04-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);
        Invoice::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id, 'purpose' => 'Tuition',
            'amount_kobo' => 3000000, 'due_date' => '2026-11-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);
        Payment::create(['invoice_id' => $first->id, 'amount_kobo' => 1000000, 'method' => 'Manual',
            'confirmed_by' => $data['bursar']->id, 'paid_at' => now(), 'receipt_number' => 'FIRST-PART']);
        Invoice::create(['student_id' => $data['student']->id, 'term_id' => $data['term']->id, 'purpose' => 'Trip', 'kind' => Invoice::KIND_ONE_OFF,
            'amount_kobo' => 9000000, 'due_date' => '2026-11-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);

        $this->assertSame(7000000, app(PaymentLedger::class)->portalSummary($data['student']->id, $data['studentUser'])['outstanding_kobo']);
    }

    public function test_overlapping_terms_use_one_latest_starting_current_term_everywhere(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'Africa/Lagos'));
        $data = $this->context();
        $later = AcademicTerm::create(['session_id' => $data['session']->id, 'name' => 'Later Start', 'start_date' => '2026-10-01', 'end_date' => '2026-12-20']);
        $item = FeeItem::create(['name' => 'Tuition']);
        FeeScheduleLine::create(['term_id' => $later->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id,
            'fee_item_id' => $item->id, 'amount_kobo' => 5000000, 'is_required' => true]);

        $this->assertSame($later->id, app(AcademicCalendar::class)->currentTerm()?->id);
        $this->assertStringContainsString('Later Start', app(PaymentLedger::class)->dashboardSummary()['term']);
        $this->assertSame($later->id, app(FeeInvoicingService::class)->automaticallyInvoice($data['student'], $data['bursar'])?->term_id);

        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => $data['studentUser']);
        $context = app(PortalContext::class)->for($request);
        $overview = app(PortalOverview::class)->for($data['studentUser'], null, $context);
        $this->assertSame('Later Start, '.$data['session']->name, $overview['term_context']);
    }

    public function test_later_enrolment_is_automatically_invoiced_when_a_required_schedule_exists(): void
    {
        $data = $this->context();
        $item = FeeItem::create(['name' => 'Tuition']);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id,
            'fee_item_id' => $item->id, 'amount_kobo' => 5000000, 'is_required' => true]);
        $invoice = app(FeeInvoicingService::class)->automaticallyInvoice($data['student'], $data['bursar']);
        $this->assertNotNull($invoice);
        $this->assertSame('automatic', $invoice->creation_source);
        $this->assertSame(5000000, $invoice->amount_kobo);
        $this->assertSame(1, $invoice->lines()->count());
    }

    public function test_fee_item_in_use_by_a_schedule_cannot_be_removed(): void
    {
        $data = $this->context();
        $item = FeeItem::create(['name' => 'Tuition']);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id,
            'fee_item_id' => $item->id, 'amount_kobo' => 5000000, 'is_required' => true]);
        $this->actingAs($data['bursar'])->from(route('fees.schedule.index'))->delete(route('fees.items.destroy', $item))
            ->assertRedirect(route('fees.schedule.index'))->assertSessionHasErrors('item');
        app(TenantContext::class)->set($data['school']->id);
        $this->assertTrue(FeeItem::whereKey($item->id)->exists());
    }

    public function test_schedule_copy_is_permissioned_idempotent_and_never_changes_existing_lines_or_invoices(): void
    {
        $data = $this->context();
        $target = AcademicTerm::create(['session_id' => $data['session']->id, 'name' => 'Second Term', 'start_date' => '2027-01-01', 'end_date' => '2027-03-31']);
        $tuition = FeeItem::create(['name' => 'Tuition']);
        $levy = FeeItem::create(['name' => 'Levy']);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id, 'fee_item_id' => $tuition->id, 'amount_kobo' => 5000000, 'is_required' => true]);
        FeeScheduleLine::create(['term_id' => $data['term']->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id, 'fee_item_id' => $levy->id, 'amount_kobo' => 200000, 'is_required' => true]);
        FeeScheduleLine::create(['term_id' => $target->id, 'scope_type' => 'level', 'academic_level_id' => $data['level']->id, 'fee_item_id' => $tuition->id, 'amount_kobo' => 5500000, 'is_required' => true]);
        $invoice = Invoice::create(['student_id' => $data['student']->id, 'term_id' => $target->id, 'purpose' => 'Existing', 'amount_kobo' => 123400, 'due_date' => '2027-02-01', 'status' => 'Unpaid', 'created_by' => $data['bursar']->id]);
        $service = app(FeeInvoicingService::class);
        $preview = $service->previewScheduleCopy($data['term']->id, $target->id, 'all');
        $this->assertSame(1, $preview['copy_count']);
        $this->assertSame(1, $preview['existing_count']);
        $report = $service->applyScheduleCopy($data['term']->id, $target->id, 'all', null, null, $data['bursar']);
        $this->assertSame($preview['copy_count'], $report['copied']);
        $this->assertSame(5500000, FeeScheduleLine::where('term_id', $target->id)->where('fee_item_id', $tuition->id)->value('amount_kobo'));
        $this->assertSame(123400, $invoice->fresh()->amount_kobo);
        $again = $service->applyScheduleCopy($data['term']->id, $target->id, 'all', null, null, $data['bursar']);
        $this->assertSame(0, $again['copied']);

        $this->actingAs($data['studentUser'])->post(route('fees.schedule.copy.preview'), [
            'source_term_id' => $data['term']->id, 'target_term_id' => $target->id, 'scope_mode' => 'all',
        ])->assertForbidden();
    }

    private function context(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $bursar = User::create(['name' => 'Bursar', 'email' => 'bursar'.uniqid().'@example.test', 'password' => 'long-password-here']);
        $role = Role::create(['name' => 'Bursar']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'manage_fee_schedule']));
        $bursar->roles()->attach($role);
        $studentUser = User::create(['name' => 'Learner', 'email' => 'learner'.uniqid().'@example.test', 'password' => 'long-password-here']);
        $level = AcademicLevel::create(['name' => 'Primary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'Primary 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-15']);

        return compact('school', 'bursar', 'studentUser', 'level', 'class', 'arm', 'student', 'session', 'term');
    }
}
