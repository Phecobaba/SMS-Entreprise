<?php

namespace Tests\Feature\Results;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\CommentRange;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\TermDefaultComment;
use App\Modules\Results\Services\ResultCards;
use App\Modules\Results\Services\ResultConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCardCommentModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_personalized_range_and_single_comments_resolve_in_the_required_order(): void
    {
        $f = $this->fixture();
        $f['section']->update(['principal_comment_mode' => 'range', 'proprietor_comment_mode' => 'single']);
        CommentRange::create(['section_id' => $f['section']->id, 'comment_type' => 'Principal', 'min_average' => 80, 'max_average' => 100, 'comment_text' => 'Range comment']);
        TermDefaultComment::create(['term_id' => $f['term']->id, 'section_id' => $f['section']->id, 'comment_type' => 'Principal', 'comment_text' => 'Single principal']);
        TermDefaultComment::create(['term_id' => $f['term']->id, 'section_id' => $f['section']->id, 'comment_type' => 'Proprietor', 'comment_text' => 'Single proprietor']);
        $this->makeResult($f, 79.0, 'Mathematics');
        $this->makeResult($f, 80.0, 'English');

        $detail = app(ResultCards::class)->detail($f['card']);
        $this->assertSame(79.5, $detail['average']);
        $this->assertSame('Range comment', $detail['principalComment']);
        $this->assertSame('Single proprietor', $detail['proprietorComment']);

        $f['card']->update(['principal_comment' => 'Typed for pupil']);
        $this->assertSame('Typed for pupil', app(ResultCards::class)->detail($f['card']->fresh())['principalComment']);
        $f['card']->update(['principal_comment' => null]);
        CommentRange::query()->delete();
        $this->assertSame('', app(ResultCards::class)->detail($f['card']->fresh())['principalComment']);

        $emptyUser = User::create(['name' => 'No Results', 'email' => 'no-results@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $emptyStudent = Student::create(['user_id' => $emptyUser->id, 'class_arm_id' => $f['arm']->id, 'admission_date' => '2026-09-01']);
        $emptyCard = ResultCard::create(['student_id' => $emptyStudent->id, 'term_id' => $f['term']->id, 'class_arm_id' => $f['arm']->id, 'compiled_at' => now()]);
        $emptyDetail = app(ResultCards::class)->detail($emptyCard);
        $this->assertNull($emptyDetail['average']);
        $this->assertSame('', $emptyDetail['principalComment']);
    }

    public function test_range_validation_section_access_proprietor_permission_and_audits_are_enforced(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['head'])->post(route('results.comment-ranges.store'), [
            'section_id' => $f['section']->id, 'comment_type' => 'Principal', 'min_average' => 0, 'max_average' => 49, 'comment_text' => 'Keep trying',
        ])->assertSessionHasNoErrors();
        $this->post(route('results.comment-ranges.store'), [
            'section_id' => $f['section']->id, 'comment_type' => 'Principal', 'min_average' => 40, 'max_average' => 60, 'comment_text' => 'Overlap',
        ])->assertSessionHasErrors('min_average');
        $this->post(route('results.comment-ranges.store'), [
            'section_id' => $f['section']->id, 'comment_type' => 'Principal', 'min_average' => 10.5, 'max_average' => 20, 'comment_text' => 'Decimal',
        ])->assertSessionHasErrors('min_average');
        $this->post(route('results.comment-ranges.store'), [
            'section_id' => $f['otherSection']->id, 'comment_type' => 'Principal', 'min_average' => 50, 'max_average' => 100, 'comment_text' => 'Forged',
        ])->assertForbidden();
        $this->post(route('results.comment-ranges.store'), [
            'section_id' => $f['section']->id, 'comment_type' => 'Proprietor', 'min_average' => 50, 'max_average' => 100, 'comment_text' => 'No proprietor access',
        ])->assertForbidden();

        $this->actingAs($f['admin'])->post(route('results.comment-ranges.store'), [
            'section_id' => $f['otherSection']->id, 'comment_type' => 'Proprietor', 'min_average' => 50, 'max_average' => 100, 'comment_text' => 'Admin range',
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $this->assertDatabaseHas('comment_ranges', ['section_id' => $f['otherSection']->id, 'comment_text' => 'Admin range']);
        $this->assertGreaterThanOrEqual(2, AuditLog::where('action', 'report_card_comment_range_saved')->count());
    }

    public function test_used_template_switches_and_configured_title_apply_to_web_pdf_and_portal_views(): void
    {
        $f = $this->fixture();
        $this->makeResult($f, 88.0, 'Mathematics');
        SchoolSetting::create(['proprietor_comment_title' => "Director's remark"]);
        $f['card']->update(['proprietor_comment' => 'Well done']);

        $this->actingAs($f['admin'])->post(route('results.templates.subject-remarks', $f['template']), [
            'show_subject_remarks' => 1, 'show_overall_total' => 0, 'show_average' => 1, 'show_position' => 0,
        ])->assertSessionHasNoErrors();
        app(TenantContext::class)->set($f['school']->id);
        $detail = app(ResultCards::class)->detail($f['card']);
        $this->assertFalse($detail['showOverallTotal']);
        $this->assertTrue($detail['showAverage']);
        $this->assertFalse($detail['showPosition']);
        $this->assertSame("Director's remark", $detail['proprietorCommentTitle']);
        $broadsheet = app(ResultCards::class)->broadsheet($f['arm']->id, $f['term']->id);

        $web = view('results.card', [...$detail, 'profile' => [], 'broadsheet' => $broadsheet])->render();
        $pdf = view('results.card-pdf', [...$detail, 'profile' => [], 'broadsheet' => $broadsheet])->render();
        $portal = view('portals.card', [...$detail, 'context' => ['student_id' => $f['student']->id], 'locked' => false, 'position' => 1, 'classSize' => 1, 'class' => 'JS1 A'])->render();
        foreach ([$web, $pdf, $portal] as $html) {
            $this->assertStringContainsString('Average / overall percentage', $html);
            $this->assertStringContainsString("Director's remark", html_entity_decode($html));
            $this->assertStringNotContainsString('Overall total:', $html);
        }
        $this->assertStringNotContainsString('<strong>Position:</strong>', $pdf);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        foreach (['manage_result_settings', 'manage_section_result_settings', 'add_principal_comment', 'add_proprietor_comment', 'view_all_sections'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        $adminRole = Role::create(['name' => 'School Admin']);
        $adminRole->permissions()->attach(Permission::whereIn('name', ['manage_result_settings', 'add_proprietor_comment', 'view_all_sections'])->pluck('id'));
        $headRole = Role::create(['name' => 'Principal']);
        $headRole->permissions()->attach(Permission::whereIn('name', ['manage_section_result_settings', 'add_principal_comment'])->pluck('id'));
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $head = User::create(['name' => 'Head', 'email' => 'head@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $studentUser = User::create(['name' => 'Pupil', 'email' => 'pupil@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $admin->roles()->attach($adminRole);
        $head->roles()->attach($headRole);
        $section = Section::create(['name' => 'Secondary', 'head_title' => 'Principal']);
        $otherSection = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $section->heads()->attach($head, ['school_id' => $school->id]);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Junior Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => '2026-09-01']);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $configuration = app(ResultConfiguration::class);
        $component = $configuration->addComponent($section->id, 'Exam', 100);
        $scheme = $configuration->addScheme($section->id, 'Scale');
        $configuration->addBand($scheme->id, 0, 100, 'A', 'Good');
        $template = $configuration->addTemplate('Template', $section->id, $level->id, null, $scheme->id, [$component->id]);
        $card = ResultCard::create(['student_id' => $student->id, 'term_id' => $term->id, 'class_arm_id' => $arm->id, 'compiled_at' => now()]);

        return compact('school', 'admin', 'head', 'section', 'otherSection', 'level', 'class', 'arm', 'student', 'term', 'component', 'template', 'card');
    }

    private function makeResult(array $f, float $percentage, string $subjectName): Result
    {
        $subject = Subject::create(['section_id' => $f['section']->id, 'name' => $subjectName]);

        return Result::create([
            'student_id' => $f['student']->id, 'class_arm_id' => $f['arm']->id, 'subject_id' => $subject->id,
            'term_id' => $f['term']->id, 'result_template_id' => $f['template']->id, 'status' => 'Approved',
            'total_score' => $percentage, 'percentage' => $percentage, 'grade' => 'A', 'locked_at' => now(),
        ]);
    }
}
