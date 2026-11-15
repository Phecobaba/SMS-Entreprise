<?php

namespace Tests\Feature\CBT;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\ExamSession;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamType;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateEntranceExamScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_sees_only_level_session_date_and_live_window_with_unlinked_warning(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-14 10:00:00', 'Africa/Lagos')->utc());
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $section = Section::create(['name' => 'Primary', 'head_title' => 'Head Teacher']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 1']);
        $otherLevel = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Primary 2']);
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'password']);
        $session = ExamSession::create([
            'name' => 'Exam day', 'starts_at' => now('Africa/Lagos')->setTime(8, 0)->utc(),
            'venue_note' => 'Hall', 'is_in_person' => true, 'section_id' => $section->id, 'created_by' => $admin->id,
        ]);
        $application = $this->application($level, $session);
        $user = User::create(['name' => 'Candidate', 'username' => 'candidate.one', 'password' => 'password']);
        $candidate = Candidate::create([
            'user_id' => $user->id, 'application_id' => $application->id, 'applied_level_id' => $level->id,
            'name' => 'Candidate', 'phone' => '08000000000', 'status' => 'Payment Confirmed',
        ]);
        $type = ExamType::create(['name' => 'Entrance', 'feeds_results' => false, 'active' => true]);
        $right = $this->exam($type, $admin, 'Right Level', now()->subHour(), now()->addHour());
        $right->levels()->attach($level, ['school_id' => $school->id]);
        $wrong = $this->exam($type, $admin, 'Wrong Level', now()->subHour(), now()->addHour());
        $wrong->levels()->attach($otherLevel, ['school_id' => $school->id]);
        $this->exam($type, $admin, 'Not open yet', now()->addHour(), now()->addHours(2))->levels()->attach($level, ['school_id' => $school->id]);
        $unlinked = $this->exam($type, $admin, 'School-wide entrance', now()->subHour(), now()->addHour());

        $this->actingAs($user)->get(route('cbt.mine'))->assertOk()
            ->assertSee('Right Level')->assertSee('School-wide entrance')
            ->assertSee('No Level linked: visible to all candidates')
            ->assertDontSee('Wrong Level')->assertDontSee('Not open yet');
        app(TenantContext::class)->set($school->id);
        $this->actingAs($user)->post(route('cbt.start', $wrong))->assertForbidden();

        app(TenantContext::class)->set($school->id);
        $application->update(['exam_session_id' => ExamSession::create([
            'name' => 'Tomorrow', 'starts_at' => now('Africa/Lagos')->addDay()->setTime(8, 0)->utc(),
            'venue_note' => 'Hall', 'is_in_person' => true, 'section_id' => $section->id, 'created_by' => $admin->id,
        ])->id]);
        app(TenantContext::class)->set($school->id);
        $this->actingAs($user)->get(route('cbt.mine'))->assertOk()->assertDontSee('Right Level')->assertDontSee('School-wide entrance');
        CarbonImmutable::setTestNow();
    }

    private function exam(ExamType $type, User $creator, string $title, $opens, $closes): Exam
    {
        return Exam::create([
            'exam_type_id' => $type->id, 'created_by' => $creator->id, 'title' => $title,
            'duration' => 30, 'status' => 'Published', 'scheduled_at' => $opens, 'closes_at' => $closes,
            'pass_mark' => 50, 'result_release_type' => 'Immediate', 'show_score' => true,
        ]);
    }

    private function application(AcademicLevel $level, ExamSession $session): Application
    {
        return Application::create([
            'public_reference' => 'APP-CANDIDATE', 'child_first_name' => 'Candidate', 'child_surname' => 'One',
            'date_of_birth' => '2017-05-10', 'gender' => 'Female', 'applied_level_id' => $level->id,
            'guardian_name' => 'Parent', 'guardian_relationship' => 'Mother', 'guardian_phone' => '08000000000',
            'guardian_email' => 'parent@example.test', 'guardian_address' => 'Lagos', 'username' => 'candidate.one',
            'status' => 'Registered', 'fee_amount_kobo' => 0, 'currency' => 'NGN',
            'access_token_hash' => hash('sha256', 'candidate-one'), 'consented_at' => now(), 'consent_version' => 'test',
            'expires_at' => now()->addDays(7), 'registered_at' => now(), 'exam_session_id' => $session->id,
        ]);
    }
}
