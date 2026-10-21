<?php

namespace Tests\Feature\Results;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CurrentSchoolProfile;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultAuditLog;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\ResultCardCommentAudit;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Services\ResultCards;
use App\Modules\Results\Services\ResultConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultApprovalLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cannot_edit_or_approve_an_approved_result_through_direct_requests(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);

        $teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $formTeacher = User::create(['name' => 'Form Teacher', 'email' => 'form-teacher@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $principal = User::create(['name' => 'Principal', 'email' => 'principal@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $learner = User::create(['name' => 'Learner', 'email' => 'learner@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $entryRole = Role::create(['name' => 'Teacher']);
        $approvalRole = Role::create(['name' => 'Principal']);
        $entry = Permission::create(['name' => 'enter_results']);
        $markAttendance = Permission::create(['name' => 'mark_attendance']);
        $approval = Permission::create(['name' => 'approve_results']);
        $settings = Permission::create(['name' => 'manage_result_settings']);
        $entryRole->permissions()->attach([$entry->id, $markAttendance->id]);
        $approvalRole->permissions()->attach([$approval->id, $settings->id]);
        $teacher->roles()->attach($entryRole);
        $formTeacher->roles()->attach($entryRole);
        $principal->roles()->attach($approvalRole);

        $section = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $section->heads()->attach($principal, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A', 'form_teacher_user_id' => $formTeacher->id]);
        $subject = Subject::create(['name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        $otherSubject = Subject::create(['name' => 'English']);
        ClassSubject::create(['class_id' => $class->id, 'subject_id' => $otherSubject->id]);
        app(ResultTeachingAssignments::class)->assign($teacher->id, $class->id, $subject->id);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-01-01', 'end_date' => '2026-03-31']);
        $student = Student::create(['user_id' => $learner->id, 'class_arm_id' => $arm->id, 'candidate_id' => null, 'admission_date' => '2026-01-01']);

        $configuration = app(ResultConfiguration::class);
        $ca = $configuration->addComponent('CA1', 15);
        $ca2 = $configuration->addComponent('CA2', 15);
        $assignment = $configuration->addComponent('Assignment', 20);
        $exam = $configuration->addComponent('Exam', 50);
        $scheme = $configuration->addScheme('Secondary grading');
        $configuration->addBand($scheme->id, 0, 49.99, 'F', 'Needs improvement');
        $configuration->addBand($scheme->id, 50, 100, 'A', 'Passed');
        $template = $configuration->addTemplate('Secondary default', $level->id, null, $scheme->id, [$ca->id, $ca2->id, $assignment->id, $exam->id]);
        $this->assertTrue($template->show_subject_remarks);

        $this->actingAs($principal)->get(route('results.configuration'))->assertOk()->assertSee('Secondary default');
        $this->actingAs($teacher)->get(route('results.index'))->assertOk()->assertSee('Results workspace');
        $this->get(route('results.new', $student->id))->assertOk()->assertSee('Enter result for Learner');
        $this->post(route('results.drafts.store'), [
            'student_id' => $student->id, 'subject_id' => $otherSubject->id, 'term_id' => $term->id,
            'scores' => [$ca->id => 10],
        ])->assertSessionHasErrors('subject_id');
        $this->actingAs($teacher)->post(route('results.drafts.store'), [
            'student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $term->id,
            'scores' => [$ca->id => 12, $ca2->id => 12, $assignment->id => 14, $exam->id => 42],
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $result = Result::firstOrFail();
        $this->assertSame('Draft', $result->status);
        $this->get(route('results.show', $result))->assertOk()->assertSee('Submit for approval');
        $validCsv = UploadedFile::fake()->createWithContent('draft-scores.csv', "student_id,subject_id,term_id,component_id,score\n{$student->id},{$subject->id},{$term->id},{$ca2->id},12\n");
        $this->post(route('results.imports.store'), ['file' => $validCsv])->assertSessionHasNoErrors();

        $this->post(route('results.submit', $result))->assertSessionHasNoErrors();
        $this->post(route('results.approve', $result))->assertForbidden();
        $this->actingAs($principal)->post(route('results.approve', $result))->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $result->refresh();
        $this->assertSame('Approved', $result->status);
        $this->assertNotNull($result->locked_at);
        $this->assertSame('80.00', $result->percentage);
        $this->assertSame('A', $result->grade);
        $this->get(route('results.show', $result))->assertOk()->assertSee('Approved and locked');

        $this->actingAs($teacher)->post(route('results.drafts.update', $result), ['scores' => [$ca->id => 1]])
            ->assertSessionHasErrors('result');
        $csv = UploadedFile::fake()->createWithContent('scores.csv', "student_id,subject_id,term_id,component_id,score\n{$student->id},{$subject->id},{$term->id},{$ca->id},1\n");
        $this->post(route('results.imports.store'), ['file' => $csv])->assertSessionHasErrors('result');
        app(TenantContext::class)->set($school->id);
        $this->assertSame('12.00', ResultItem::where('result_id', $result->id)->where('assessment_component_id', $ca->id)->firstOrFail()->score);
        $this->assertSame(['Upload', 'Edit', 'Submit', 'Approve'], ResultAuditLog::where('result_id', $result->id)->orderBy('id')->pluck('action')->all());

        $card = ResultCard::where('student_id', $student->id)->where('term_id', $term->id)->firstOrFail();
        $this->actingAs($teacher)->post(route('results.cards.comments', $card), ['class_teacher_comment' => 'Subject teacher attempt'])
            ->assertForbidden();
        app(TenantContext::class)->set($school->id);
        $this->assertNull($card->fresh()->class_teacher_comment);
        $this->actingAs($formTeacher)->get(route('results.cards.show', $card))->assertOk()->assertSee('Class teacher comment');
        $this->post(route('results.cards.comments', $card), ['class_teacher_comment' => 'Form teacher only'])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Form teacher only', $card->fresh()->class_teacher_comment);
        $this->assertSame($formTeacher->id, ResultCardCommentAudit::where('result_card_id', $card->id)->where('comment_type', 'Class Teacher')->firstOrFail()->user_id);
        $this->actingAs($principal)->get(route('results.cards.show', $card))->assertOk()->assertSee('Class broadsheet');
        app(TenantContext::class)->set($school->id);
        $commentPermission = Permission::create(['name' => 'add_principal_comment']);
        $proprietorPermission = Permission::create(['name' => 'add_proprietor_comment']);
        $approvalRole->permissions()->attach($commentPermission);
        $this->post(route('results.card-defaults.store'), [
            'term_id' => $term->id, 'section_id' => $section->id, 'comment_type' => 'Proprietor', 'comment_text' => 'Not permitted',
        ])->assertForbidden();
        app(TenantContext::class)->set($school->id);
        $approvalRole->permissions()->attach($proprietorPermission);
        $this->post(route('results.card-defaults.store'), [
            'term_id' => $term->id, 'section_id' => $section->id, 'comment_type' => 'Principal', 'comment_text' => str_repeat('P', 300),
        ])->assertSessionHasNoErrors();
        $this->post(route('results.card-defaults.store'), [
            'term_id' => $term->id, 'section_id' => $section->id, 'comment_type' => 'Proprietor', 'comment_text' => str_repeat('Q', 300),
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        Storage::fake('public');
        Storage::disk('public')->put('school-logos/test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9gmR4AAAAASUVORK5CYII='));
        SchoolSetting::create(['logo_path' => 'school-logos/test.png', 'address' => '1 Oakfield Road', 'motto' => 'Excellence']);
        $this->actingAs($learner)->get(route('results.mine.show', $card))->assertOk()
            ->assertSee('Report card locked')->assertDontSee('Class broadsheet')
            ->assertDontSee('Mathematics')->assertDontSee('80.00%')->assertDontSee('Passed');
        $this->get(route('results.mine.pdf', $card))->assertForbidden();
        app(TenantContext::class)->set($school->id);
        $paymentsPermission = Permission::firstOrCreate(['name' => 'manage_payments']);
        $approvalRole->permissions()->attach($paymentsPermission);
        $invoice = app(PaymentLedger::class)->invoice([
            'student_id' => $student->id, 'term_id' => $term->id,
            'purpose' => 'Tuition', 'amount_kobo' => 2500000,
            'due_date' => today()->toDateString(),
        ], $principal);
        $this->actingAs($principal)->post(route('payments.manual', $invoice))->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertSame('Paid', $invoice->fresh()->status);
        $this->assertSame(2500000, Payment::where('invoice_id', $invoice->id)->firstOrFail()->amount_kobo);
        $this->actingAs($learner)->get(route('results.mine.show', $card))->assertOk()
            ->assertSee('Mathematics')->assertDontSee('Class broadsheet')->assertDontSee('Results workspace');
        $this->get(route('results.mine.pdf', $card))->assertOk();
        app(TenantContext::class)->set($school->id);
        foreach (range(2, 20) as $number) {
            $failing = $number === 2;
            $extraSubject = Subject::create(['name' => 'Secondary Subject '.$number]);
            ClassSubject::create(['class_id' => $class->id, 'subject_id' => $extraSubject->id]);
            $extra = Result::create([
                'student_id' => $student->id, 'class_arm_id' => $arm->id, 'subject_id' => $extraSubject->id,
                'term_id' => $term->id, 'result_template_id' => $result->result_template_id,
                'status' => 'Approved', 'total_score' => $failing ? 40 : 80, 'percentage' => $failing ? 40 : 80,
                'grade' => $failing ? 'F' : 'A', 'remark' => 'Stale snapshot not used', 'locked_at' => now(),
            ]);
            ResultItem::create(['result_id' => $extra->id, 'assessment_component_id' => $ca->id, 'score' => $failing ? 6 : 12]);
            ResultItem::create(['result_id' => $extra->id, 'assessment_component_id' => $ca2->id, 'score' => $failing ? 6 : 12]);
            ResultItem::create(['result_id' => $extra->id, 'assessment_component_id' => $assignment->id, 'score' => $failing ? 8 : 14]);
            ResultItem::create(['result_id' => $extra->id, 'assessment_component_id' => $exam->id, 'score' => $failing ? 20 : 42]);
        }
        $cards = app(ResultCards::class);
        $row = $cards->broadsheet($arm->id, $term->id)[0];
        $this->assertSame(20, $row['subjects']);
        $this->assertSame(78.0, $row['average']);
        $this->assertSame(1, $row['position']);
        $detail = $cards->detail($card);
        $this->assertSame(str_repeat('P', 300), $detail['principalComment']);
        $this->assertSame(str_repeat('Q', 300), $detail['proprietorComment']);
        $profile = app(CurrentSchoolProfile::class)->printProfile();
        $this->assertSame('Oakfield', $profile['name']);
        $this->assertSame('1 Oakfield Road', $profile['address']);
        $this->assertStringStartsWith('data:image/png;base64,', $profile['logo_data_uri']);
        $this->assertTrue($detail['showSubjectRemarks']);
        $this->assertSame('Passed', $detail['subjectRemarks'][$result->id]);
        $this->assertContains('Needs improvement', $detail['subjectRemarks']->values()->all());
        $onHtml = view('results.card-pdf', [...$detail, 'profile' => $profile, 'broadsheet' => $cards->broadsheet($arm->id, $term->id)])->render();
        $this->assertStringContainsString('<th>Remark</th>', $onHtml);
        $this->assertStringContainsString('Needs improvement', $onHtml);
        $this->assertStringContainsString('Passed', $onHtml);
        $this->assertStringNotContainsString('Stale snapshot not used', $onHtml);
        foreach (['CA1', 'CA2', 'Assignment', 'Exam', 'data:image/png;base64', str_repeat('P', 300), str_repeat('Q', 300)] as $requiredContent) {
            $this->assertStringContainsString($requiredContent, $onHtml);
        }
        $this->actingAs($principal)->get(route('results.cards.pdf', $card))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($principal)->post(route('results.templates.subject-remarks', $template->id), ['show_subject_remarks' => 0])
            ->assertSessionHasNoErrors();
        app(TenantContext::class)->set($school->id);
        $this->assertFalse($template->fresh()->show_subject_remarks);
        $offDetail = $cards->detail($card);
        $this->assertFalse($offDetail['showSubjectRemarks']);
        $offHtml = view('results.card-pdf', [...$offDetail, 'profile' => $profile, 'broadsheet' => $cards->broadsheet($arm->id, $term->id)])->render();
        $this->assertStringNotContainsString('<th>Remark</th>', $offHtml);
        $this->assertStringNotContainsString('Needs improvement', $offHtml);
        $this->get(route('results.cards.pdf', $card))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
