<?php

namespace Tests\Feature\Admissions;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Models\StudentRegistrationNumberHistory;
use App\Modules\Admissions\Services\IncomingAdmissionService;
use App\Modules\Admissions\Services\PromotionService;
use App\Modules\Admissions\Services\RegistrationNumbers;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_scope_rejects_edited_source_and_destination_ids_while_admin_can_promote(): void
    {
        $f = $this->fixture();
        $student = $this->student('Scoped student', $f['primaryOneArm']);

        $payload = $this->payload($f, $student, $f['primaryTwo'], $f['primaryTwoArm']);
        $this->actingAs($f['head'])->post(route('academics.promotion.review'), [
            ...$payload, 'source_class_id' => $f['secondaryFinal']->id,
        ])->assertForbidden();
        $this->post(route('academics.promotion.review'), [
            ...$payload, 'class' => [$student->id => $f['secondaryFinal']->id],
            'arm' => [$student->id => $f['secondaryFinalArm']->id],
        ])->assertForbidden();

        $this->actingAs($f['admin'])->post(route('academics.promotion.review'), $payload)
            ->assertOk()->assertSee('Will move 1, will stay 0');
        $this->post(route('academics.promotion.apply'), $payload)->assertRedirect();
        $this->assertSame($f['primaryTwoArm']->id, $student->fresh()->class_arm_id);
    }

    public function test_promotion_is_refused_until_every_section_is_arranged(): void
    {
        $f = $this->fixture();
        $student = $this->student('Waiting student', $f['primaryOneArm']);
        $f['secondary']->update(['classes_arranged_at' => null, 'classes_arranged_by' => null]);

        $this->actingAs($f['admin'])->get(route('academics.promotion.index'))
            ->assertOk()->assertSee('Arrange your classes first');
        app(TenantContext::class)->set($f['school']->id);

        $this->expectException(ValidationException::class);
        app(PromotionService::class)->preview(
            $f['admin'], $f['primaryOne']->id, null,
            [$student->id => ['class_id' => $f['primaryTwo']->id, 'arm_id' => $f['primaryTwoArm']->id]],
        );
    }

    public function test_primary_final_becomes_awaiting_and_repeat_apply_is_idempotent_with_history_unchanged(): void
    {
        $f = $this->fixture();
        $student = $this->student('Primary leaver', $f['primaryTwoArm']);
        app(RegistrationNumbers::class)->assignAutomatic($student, $f['admin']);
        $number = $student->fresh()->registration_number;
        [$card, $attendance, $invoice] = $this->history($f, $student, $f['primaryTwoArm']);

        $service = app(PromotionService::class);
        $placements = [$student->id => []];
        $preview = $service->preview($f['head'], $f['primaryTwo']->id, null, $placements);
        $applied = $service->apply($f['head'], $f['primaryTwo']->id, null, $placements);

        $this->assertSame(1, $preview['moved']);
        $this->assertSame($preview['moved'], $applied['moved']);
        $this->assertSame(Student::STATUS_AWAITING, $student->fresh()->status);
        $this->assertSame($f['primaryTwoArm']->id, $student->fresh()->awaiting_from_class_arm_id);
        $this->assertNotNull($student->fresh()->awaiting_admission_at);
        $this->assertSame($number, $student->fresh()->registration_number);
        $this->assertSame(0, app(StudentDirectory::class)->countForArm($f['primaryTwoArm']->id));
        $this->assertCount(0, app(StudentDirectory::class)->rosterForArm($f['primaryTwoArm']->id));
        $this->assertSame(0, app(StudentDirectory::class)->count($f['admin']));
        $this->assertSame($f['primaryTwoArm']->id, $card->fresh()->class_arm_id);
        $this->assertSame($f['primaryTwoArm']->id, $attendance->fresh()->class_arm_id);
        $this->assertSame($student->id, $invoice->fresh()->student_id);

        $rerun = $service->apply($f['head'], $f['primaryTwo']->id, null, $placements);
        $this->assertSame(0, $rerun['moved']);
        $this->assertSame(1, $rerun['skipped']);
        $this->assertSame(1, AuditLog::where('action', 'student_promoted')->count());
    }

    public function test_final_school_class_graduates_and_past_report_card_and_receipt_remain_available(): void
    {
        $f = $this->fixture();
        $student = $this->student('Graduate', $f['secondaryFinalArm']);
        app(RegistrationNumbers::class)->assignAutomatic($student, $f['admin']);
        $number = $student->fresh()->registration_number;
        [$card, $attendance, $invoice] = $this->history($f, $student, $f['secondaryFinalArm']);

        app(PromotionService::class)->apply($f['admin'], $f['secondaryFinal']->id, null, [$student->id => []]);

        $this->assertSame(Student::STATUS_GRADUATED, $student->fresh()->status);
        $this->assertSame($number, $student->fresh()->registration_number);
        $this->assertSame(0, app(StudentDirectory::class)->countForArm($f['secondaryFinalArm']->id));
        $this->assertSame($f['secondaryFinalArm']->id, $card->fresh()->class_arm_id);
        $this->assertSame($f['secondaryFinalArm']->id, $attendance->fresh()->class_arm_id);
        $this->assertSame($student->id, $invoice->fresh()->student_id);
        $this->actingAs($student->user)->get(route('results.mine.show', $card))->assertOk();
        $this->get(route('payments.mine.index'))->assertOk()->assertSee($invoice->payment->receipt_number)->assertDontSee('Pay now');
        $this->get(route('attendance.mine'))->assertOk()->assertSee('Present');
    }

    public function test_ambiguous_next_step_has_no_suggestion_and_requires_each_destination(): void
    {
        $f = $this->fixture();
        $other = SchoolClass::create([
            'academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 2 Blue', 'position' => 2,
        ]);
        $otherArm = ClassArm::create(['class_id' => $other->id, 'name' => 'A']);
        $student = $this->student('Ambiguous student', $f['primaryOneArm']);

        $suggestion = app(PromotionService::class)->suggestion($student->load('classArm'), $f['primaryOne']);
        $this->assertNull($suggestion['class']);
        try {
            app(PromotionService::class)->preview($f['admin'], $f['primaryOne']->id, null, [$student->id => []]);
            $this->fail('An ambiguous promotion must require an explicit destination.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destinations.'.$student->id, $exception->errors());
        }

        $plan = app(PromotionService::class)->apply($f['admin'], $f['primaryOne']->id, null, [
            $student->id => ['class_id' => $other->id, 'arm_id' => $otherArm->id],
        ]);
        $this->assertSame(1, $plan['moved']);
        $this->assertSame($otherArm->id, $student->fresh()->class_arm_id);
    }

    public function test_incoming_scope_blocks_heads_from_other_sections_and_admin_can_accept(): void
    {
        $f = $this->fixture();
        $student = $this->waitingStudent('Incoming scope', $f['primaryTwoArm']);
        $outside = $this->waitingStudent('Wrong incoming step', $f['primaryOneArm']);
        $service = app(IncomingAdmissionService::class);
        $valid = [$student->id => ['class_id' => $f['secondaryFinal']->id, 'arm_id' => $f['secondaryFinalArm']->id]];

        $this->actingAs($f['head'])->get(route('academics.promotion.incoming.index', ['section' => $f['secondary']->id]))->assertForbidden();
        app(TenantContext::class)->set($f['school']->id);
        $this->actingAs($f['secondaryHead'])->get(route('academics.promotion.incoming.index'))
            ->assertOk()->assertSee('Incoming from Primary')->assertSee('Incoming scope');
        app(TenantContext::class)->set($f['school']->id);
        try {
            $service->previewAccept($f['secondaryHead'], $f['secondary']->id, [
                $student->id => ['class_id' => $f['primaryTwo']->id, 'arm_id' => $f['primaryTwoArm']->id],
            ]);
            $this->fail('A destination outside the receiving section must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('destinations.'.$student->id, $exception->errors());
        }
        try {
            $service->previewNotContinuing($f['secondaryHead'], $f['secondary']->id, [$outside->id], false);
            $this->fail('A head must not act on a pupil outside the incoming list for their section.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('students', $exception->errors());
        }

        $preview = $service->previewAccept($f['admin'], $f['secondary']->id, $valid);
        $applied = $service->accept($f['admin'], $f['secondary']->id, $valid);
        $this->assertSame(1, $preview['accepted']);
        $this->assertSame($preview['accepted'], $applied['accepted']);
        $this->assertSame(Student::STATUS_ACTIVE, $student->fresh()->status);
        $this->assertSame($f['secondaryFinalArm']->id, $student->fresh()->class_arm_id);
    }

    public function test_accepting_incoming_pupil_is_idempotent_and_preview_matches_apply(): void
    {
        $f = $this->fixture();
        $student = $this->waitingStudent('Idempotent incoming', $f['primaryTwoArm']);
        $service = app(IncomingAdmissionService::class);
        $placements = [$student->id => ['class_id' => $f['secondaryFinal']->id, 'arm_id' => $f['secondaryFinalArm']->id]];

        $preview = $service->previewAccept($f['secondaryHead'], $f['secondary']->id, $placements);
        $applied = $service->accept($f['secondaryHead'], $f['secondary']->id, $placements);
        $rerun = $service->accept($f['secondaryHead'], $f['secondary']->id, $placements);

        $this->assertSame($preview['accepted'], $applied['accepted']);
        $this->assertSame(1, $applied['accepted']);
        $this->assertSame(0, $rerun['accepted']);
        $this->assertSame(1, $rerun['unchanged']);
        $this->assertSame(1, AuditLog::where('action', 'student_incoming_accepted')->count());
    }

    public function test_three_sections_use_scoped_heads_dynamic_incoming_labels_and_registration_codes(): void
    {
        $f = $this->fixture();
        $earlyHead = User::create(['name' => 'Early Years Head', 'email' => 'early-head@example.test', 'password' => 'long-password-here']);
        $earlyHead->roles()->attach(Role::where('name', 'Principal')->firstOrFail());
        $early = Section::create([
            'name' => 'Early Years', 'head_title' => 'Early Years Lead', 'learner_term' => 'Pupil',
            'registration_code' => 'EYS', 'classes_arranged_by' => $f['admin']->id, 'classes_arranged_at' => now(),
        ]);
        $early->heads()->attach($earlyHead, ['school_id' => $f['school']->id]);
        $earlyLevel = AcademicLevel::create(['section_id' => $early->id, 'name' => 'Reception', 'registration_code' => 'REC']);
        $earlyClass = SchoolClass::create(['academic_level_id' => $earlyLevel->id, 'name' => 'Reception', 'position' => 0]);
        $earlyArm = ClassArm::create(['class_id' => $earlyClass->id, 'name' => 'Sun']);
        $f['primary']->update(['registration_code' => 'PRI']);
        $f['primaryLevel']->update(['registration_code' => 'P1']);
        app(RegistrationNumbers::class)->settings()->update([
            'registration_include_school' => false,
            'registration_include_section' => true,
            'registration_include_level' => false,
            'registration_include_year' => false,
            'registration_separator' => '-',
            'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
        ]);
        $student = $this->student('Early Years pupil', $earlyArm);
        app(RegistrationNumbers::class)->assignAutomatic($student, $f['admin']);
        $this->assertStringStartsWith('EYS-', $student->fresh()->registration_number);

        $this->actingAs($earlyHead)->get(route('academics.promotion.index'))
            ->assertOk()->assertSee('Early Years')->assertDontSee('Primary 5');
        app(TenantContext::class)->set($f['school']->id);
        app(PromotionService::class)->apply($earlyHead, $earlyClass->id, null, [$student->id => []]);
        $this->assertSame(Student::STATUS_AWAITING, $student->fresh()->status);

        $this->actingAs($f['head'])->get(route('academics.promotion.incoming.index'))
            ->assertOk()
            ->assertSee('Incoming from Early Years')
            ->assertSee('Early Years pupil');
        app(TenantContext::class)->set($f['school']->id);
        app(IncomingAdmissionService::class)->accept($f['head'], $f['primary']->id, [
            $student->id => ['class_id' => $f['primaryOne']->id, 'arm_id' => $f['primaryOneArm']->id],
        ]);

        $this->assertSame(Student::STATUS_ACTIVE, $student->fresh()->status);
        $this->assertSame($f['primaryOneArm']->id, $student->fresh()->class_arm_id);
        $this->assertStringStartsWith('PRI-', $student->fresh()->registration_number);
        $this->assertSame('Pupil', $early->fresh()->learner_term);
        $this->assertSame('Early Years Lead', $early->fresh()->head_title);
    }

    public function test_mark_all_remaining_changes_only_pupils_still_waiting_for_that_section(): void
    {
        $f = $this->fixture();
        $first = $this->waitingStudent('Waiting first', $f['primaryTwoArm']);
        $second = $this->waitingStudent('Waiting second', $f['primaryTwoArm']);
        $active = $this->student('Already active', $f['secondaryFinalArm']);
        $otherStep = $this->waitingStudent('Different step', $f['primaryOneArm']);
        $service = app(IncomingAdmissionService::class);

        $preview = $service->previewNotContinuing($f['secondaryHead'], $f['secondary']->id, [], true);
        $applied = $service->markNotContinuing($f['secondaryHead'], $f['secondary']->id, [], true);

        $this->assertSame(2, $preview['graduated']);
        $this->assertSame($preview['graduated'], $applied['graduated']);
        $this->assertSame(Student::STATUS_GRADUATED, $first->fresh()->status);
        $this->assertSame(Student::STATUS_GRADUATED, $second->fresh()->status);
        $this->assertSame(Student::STATUS_ACTIVE, $active->fresh()->status);
        $this->assertSame(Student::STATUS_AWAITING, $otherStep->fresh()->status);
    }

    public function test_history_remains_readable_after_acceptance_and_not_continuing(): void
    {
        $f = $this->fixture();
        $accepted = $this->waitingStudent('Accepted history', $f['primaryTwoArm']);
        $notContinuing = $this->waitingStudent('Not continuing history', $f['primaryTwoArm']);
        [$acceptedCard, $acceptedAttendance, $acceptedInvoice] = $this->history($f, $accepted, $f['primaryTwoArm']);
        [$graduateCard, $graduateAttendance, $graduateInvoice] = $this->history($f, $notContinuing, $f['primaryTwoArm']);
        $service = app(IncomingAdmissionService::class);

        $service->accept($f['secondaryHead'], $f['secondary']->id, [
            $accepted->id => ['class_id' => $f['secondaryFinal']->id, 'arm_id' => $f['secondaryFinalArm']->id],
        ]);
        $service->markNotContinuing($f['secondaryHead'], $f['secondary']->id, [$notContinuing->id], false);

        $this->assertSame($f['primaryTwoArm']->id, $acceptedCard->fresh()->class_arm_id);
        $this->assertSame($f['primaryTwoArm']->id, $acceptedAttendance->fresh()->class_arm_id);
        $this->assertSame($accepted->id, $acceptedInvoice->fresh()->student_id);
        $this->assertSame($f['primaryTwoArm']->id, $graduateCard->fresh()->class_arm_id);
        $this->assertSame($f['primaryTwoArm']->id, $graduateAttendance->fresh()->class_arm_id);
        $this->assertSame($notContinuing->id, $graduateInvoice->fresh()->student_id);

        $this->actingAs($accepted->user)->get(route('results.mine.show', $acceptedCard))->assertOk();
        $this->get(route('attendance.mine'))->assertOk()->assertSee('Present');
        $this->get(route('payments.mine.index'))->assertOk()->assertSee($acceptedInvoice->payment->receipt_number);
        app(TenantContext::class)->set($f['school']->id);
        $this->actingAs($notContinuing->user)->get(route('results.mine.show', $graduateCard))->assertOk();
        $this->get(route('attendance.mine'))->assertOk()->assertSee('Present');
        $this->get(route('payments.mine.index'))->assertOk()->assertSee($graduateInvoice->payment->receipt_number);
    }

    public function test_all_movement_policies_and_same_scope_rule_use_stored_allocation(): void
    {
        $f = $this->fixture();
        $newLevel = AcademicLevel::create([
            'section_id' => $f['primary']->id, 'name' => 'Primary next', 'registration_code' => 'P2',
        ]);
        $f['primaryLevel']->update(['registration_code' => 'P1']);
        $f['primary']->update(['registration_code' => 'PRI']);
        $f['primaryTwo']->update(['academic_level_id' => $newLevel->id]);
        $settings = app(RegistrationNumbers::class)->settings();
        $settings->update([
            'registration_include_school' => false, 'registration_include_section' => false,
            'registration_include_level' => true, 'registration_separator' => '-',
            'registration_number_length' => 4, 'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
        ]);

        $keepDigits = $this->student('Odd number text', $f['primaryOneArm']);
        StudentRegistrationNumberHistory::create([
            'student_id' => $keepDigits->id, 'old_number' => null, 'new_number' => 'NOT-A-PARSED-SEQUENCE',
            'sequence_value' => 37, 'issued_year' => 2024, 'section_code' => 'PRI', 'level_code' => 'P1',
            'changed_by' => $f['admin']->id, 'changed_at' => now(),
        ]);
        $keepDigits->update(['registration_number' => 'NOT-A-PARSED-SEQUENCE']);
        app(PromotionService::class)->moveOne($f['admin'], $keepDigits->id, $f['primaryTwo']->id, $f['primaryTwoArm']->id);
        $this->assertSame('P2-0037', $keepDigits->fresh()->registration_number);

        $keepWhole = $this->student('Keep whole', $f['primaryOneArm']);
        $settings->update(['registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX]);
        app(RegistrationNumbers::class)->assignAutomatic($keepWhole, $f['admin']);
        $whole = $keepWhole->fresh()->registration_number;
        $settings->update(['registration_move_policy' => RegistrationNumbers::KEEP_WHOLE_NUMBER]);
        app(PromotionService::class)->moveOne($f['admin'], $keepWhole->id, $f['primaryTwo']->id, $f['primaryTwoArm']->id);
        $this->assertSame($whole, $keepWhole->fresh()->registration_number);

        $newSequence = $this->student('New sequence', $f['primaryOneArm']);
        $settings->update(['registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX]);
        app(RegistrationNumbers::class)->assignAutomatic($newSequence, $f['admin']);
        $before = $newSequence->fresh()->registration_number;
        $settings->update(['registration_move_policy' => RegistrationNumbers::NEW_PREFIX_SEQUENCE]);
        app(PromotionService::class)->moveOne($f['admin'], $newSequence->id, $f['primaryTwo']->id, $f['primaryTwoArm']->id);
        $this->assertNotSame($before, $newSequence->fresh()->registration_number);
        $this->assertStringStartsWith('P2-', $newSequence->fresh()->registration_number);

        $sameScope = $this->student('Same level', $f['primaryOneArm']);
        $settings->update(['registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX]);
        app(RegistrationNumbers::class)->assignAutomatic($sameScope, $f['admin']);
        $same = $sameScope->fresh()->registration_number;
        $parallel = SchoolClass::create([
            'academic_level_id' => $f['primaryLevel']->id, 'name' => 'Primary 5 B', 'position' => 1,
        ]);
        $parallelArm = ClassArm::create(['class_id' => $parallel->id, 'name' => 'A']);
        app(PromotionService::class)->moveOne($f['admin'], $sameScope->id, $parallel->id, $parallelArm->id);
        $this->assertSame($same, $sameScope->fresh()->registration_number);
    }

    public function test_preview_matches_apply_and_clash_adjustment_is_visible_and_audited(): void
    {
        $f = $this->fixture();
        $newLevel = AcademicLevel::create(['section_id' => $f['primary']->id, 'name' => 'Primary 2', 'registration_code' => 'P2']);
        $f['primaryLevel']->update(['registration_code' => 'P1']);
        $f['primaryTwo']->update(['academic_level_id' => $newLevel->id]);
        $numbers = app(RegistrationNumbers::class);
        $numbers->settings()->update([
            'registration_include_school' => false, 'registration_include_level' => true,
            'registration_include_section' => false, 'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
        ]);
        $student = $this->student('Clash pupil', $f['primaryOneArm']);
        $numbers->assignAutomatic($student, $f['admin']);
        StudentRegistrationNumberHistory::create([
            'student_id' => $student->id, 'old_number' => 'RESERVED', 'new_number' => 'P2-0001',
            'sequence_value' => 999, 'issued_year' => 2024, 'level_code' => 'P2',
            'changed_by' => $f['admin']->id, 'changed_at' => now()->subDay(),
        ]);
        $placements = [$student->id => ['class_id' => $f['primaryTwo']->id, 'arm_id' => $f['primaryTwoArm']->id]];

        $preview = app(PromotionService::class)->preview($f['admin'], $f['primaryOne']->id, null, $placements);
        $expected = $preview['rows']->first()['registration'];
        $this->assertTrue($expected['adjusted']);
        $applied = app(PromotionService::class)->apply($f['admin'], $f['primaryOne']->id, null, $placements);
        $this->assertSame($expected['new_number'], $applied['rows']->first()['registration']['new_number']);
        $this->assertSame($expected['new_number'], $student->fresh()->registration_number);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student_registration_number_changed', 'subject_id' => $student->id,
        ]);
        $this->assertSame(
            'movement_policy_clash_adjustment',
            AuditLog::where('action', 'student_registration_number_changed')->latest('id')->firstOrFail()->metadata['reason']
        );
    }

    public function test_incoming_acceptance_numbers_atomically_and_missing_code_refuses_cleanly(): void
    {
        $f = $this->fixture();
        $f['secondary']->update(['registration_code' => 'SEC']);
        $f['secondaryLevel']->update(['registration_code' => 'S3']);
        app(RegistrationNumbers::class)->settings()->update([
            'registration_include_school' => false, 'registration_include_section' => true,
            'registration_include_level' => true, 'registration_include_year' => true,
            'registration_year_digits' => 4, 'registration_move_policy' => RegistrationNumbers::NEW_PREFIX_SEQUENCE,
        ]);
        $student = $this->waitingStudent('New incoming', $f['primaryTwoArm']);
        $placements = [$student->id => ['class_id' => $f['secondaryFinal']->id, 'arm_id' => $f['secondaryFinalArm']->id]];
        $preview = app(IncomingAdmissionService::class)->previewAccept($f['secondaryHead'], $f['secondary']->id, $placements);
        $expected = $preview['rows']->first()['registration']['new_number'];
        app(IncomingAdmissionService::class)->accept($f['secondaryHead'], $f['secondary']->id, $placements);
        $this->assertSame($expected, $student->fresh()->registration_number);
        $this->assertMatchesRegularExpression('/^SEC-S3-'.now('Africa/Lagos')->format('Y').'-\d+$/', $student->fresh()->registration_number);

        $missing = $this->waitingStudent('Missing code', $f['primaryTwoArm']);
        $f['secondaryLevel']->update(['registration_code' => null]);
        try {
            app(IncomingAdmissionService::class)->accept($f['secondaryHead'], $f['secondary']->id, [
                $missing->id => ['class_id' => $f['secondaryFinal']->id, 'arm_id' => $f['secondaryFinalArm']->id],
            ]);
            $this->fail('A required missing Level code must refuse acceptance.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Level code is missing', collect($exception->errors())->flatten()->first());
        }
        $this->assertSame(Student::STATUS_AWAITING, $missing->fresh()->status);
        $this->assertNull($missing->fresh()->registration_number);
    }

    public function test_numbering_failure_rolls_back_every_movement_and_number_in_the_batch(): void
    {
        $f = $this->fixture();
        $nextLevel = AcademicLevel::create(['section_id' => $f['primary']->id, 'name' => 'Next', 'registration_code' => 'NXT']);
        $f['primaryLevel']->update(['registration_code' => 'CUR']);
        $f['primaryTwo']->update(['academic_level_id' => $nextLevel->id]);
        app(RegistrationNumbers::class)->settings()->update([
            'registration_include_school' => false, 'registration_include_level' => true,
            'registration_move_policy' => RegistrationNumbers::KEEP_DIGITS_CHANGE_PREFIX,
        ]);
        $first = $this->student('Rollback first', $f['primaryOneArm']);
        app(RegistrationNumbers::class)->assignAutomatic($first, $f['admin']);
        $firstNumber = $first->fresh()->registration_number;
        $second = $this->student('Rollback second', $f['primaryOneArm']);
        $second->update(['registration_number' => 'MANUAL-WITHOUT-ALLOCATION']);
        $placements = [
            $first->id => ['class_id' => $f['primaryTwo']->id, 'arm_id' => $f['primaryTwoArm']->id],
            $second->id => ['class_id' => $f['primaryTwo']->id, 'arm_id' => $f['primaryTwoArm']->id],
        ];

        try {
            app(PromotionService::class)->apply($f['admin'], $f['primaryOne']->id, null, $placements);
            $this->fail('A missing stored sequence must roll back the batch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_number', $exception->errors());
        }
        $this->assertSame($f['primaryOneArm']->id, $first->fresh()->class_arm_id);
        $this->assertSame($f['primaryOneArm']->id, $second->fresh()->class_arm_id);
        $this->assertSame($firstNumber, $first->fresh()->registration_number);
        $this->assertSame('MANUAL-WITHOUT-ALLOCATION', $second->fresh()->registration_number);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        SchoolSetting::create(['result_access_payment_rule' => 'None']);
        $promotion = Permission::firstOrCreate(['name' => 'manage_promotions']);
        $viewAll = Permission::firstOrCreate(['name' => 'view_all_sections']);
        $head = User::create(['name' => 'Primary Head', 'email' => 'promotion-head@example.test', 'password' => 'long-password-here']);
        $secondaryHead = User::create(['name' => 'Secondary Head', 'email' => 'secondary-promotion-head@example.test', 'password' => 'long-password-here']);
        $admin = User::create(['name' => 'Admin', 'email' => 'promotion-admin@example.test', 'password' => 'long-password-here']);
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach($promotion);
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach([$promotion->id, $viewAll->id]);
        $head->roles()->attach($headRole);
        $secondaryHead->roles()->attach($headRole);
        $admin->roles()->attach($adminRole);
        $primary = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher', 'classes_arranged_by' => $admin->id, 'classes_arranged_at' => now()]);
        $secondary = Section::create(['name' => 'Secondary', 'head_title' => 'Principal', 'classes_arranged_by' => $admin->id, 'classes_arranged_at' => now()]);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $secondary->heads()->attach($secondaryHead, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Secondary']);
        $primaryOne = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 5', 'position' => 1]);
        $primaryTwo = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 6', 'position' => 2]);
        $secondaryFinal = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'SS 3', 'position' => 3]);
        $primaryOneArm = ClassArm::create(['class_id' => $primaryOne->id, 'name' => 'A']);
        $primaryTwoArm = ClassArm::create(['class_id' => $primaryTwo->id, 'name' => 'A']);
        $secondaryFinalArm = ClassArm::create(['class_id' => $secondaryFinal->id, 'name' => 'A']);

        return compact('school', 'head', 'secondaryHead', 'admin', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel', 'primaryOne', 'primaryTwo', 'secondaryFinal', 'primaryOneArm', 'primaryTwoArm', 'secondaryFinalArm');
    }

    private function student(string $name, ClassArm $arm): Student
    {
        $user = User::create(['name' => $name, 'email' => str($name)->slug().'-promotion@example.test', 'password' => 'long-password-here']);

        return Student::create(['user_id' => $user->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
    }

    private function waitingStudent(string $name, ClassArm $sourceArm): Student
    {
        $student = $this->student($name, $sourceArm);
        $student->update([
            'status' => Student::STATUS_AWAITING,
            'awaiting_from_class_arm_id' => $sourceArm->id,
            'awaiting_admission_at' => now()->subDays(3),
        ]);

        return $student->fresh();
    }

    private function history(array $f, Student $student, ClassArm $arm): array
    {
        $session = AcademicSession::firstOrCreate(['name' => '2026/2027'], ['start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::firstOrCreate(['session_id' => $session->id, 'name' => 'First Term'], ['start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $card = ResultCard::create(['student_id' => $student->id, 'term_id' => $term->id, 'class_arm_id' => $arm->id, 'compiled_at' => now()]);
        $attendance = AttendanceRecord::create(['student_id' => $student->id, 'class_arm_id' => $arm->id, 'date' => '2026-10-16', 'session' => 'Morning', 'status' => 'Present', 'marked_by' => $f['admin']->id]);
        $invoice = Invoice::create(['student_id' => $student->id, 'term_id' => $term->id, 'purpose' => 'Tuition', 'amount_kobo' => 100000, 'due_date' => today(), 'status' => 'Paid', 'created_by' => $f['admin']->id]);
        Payment::create(['invoice_id' => $invoice->id, 'amount_kobo' => 100000, 'method' => 'Manual', 'confirmed_by' => $f['admin']->id, 'paid_at' => now(), 'receipt_number' => 'OAK-TEST-'.$student->id]);

        return [$card, $attendance, $invoice->load('payment')];
    }

    private function payload(array $f, Student $student, SchoolClass $destination, ClassArm $arm): array
    {
        return [
            'section_id' => $f['primary']->id,
            'source_class_id' => $f['primaryOne']->id,
            'students' => [$student->id],
            'class' => [$student->id => $destination->id],
            'arm' => [$student->id => $arm->id],
        ];
    }
}
