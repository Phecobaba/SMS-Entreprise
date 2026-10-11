<?php

namespace Tests\Feature\ENotes;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\ENotes\Models\ENote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentENoteScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_and_download_only_own_class_notes(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $studentUser = User::create(['name' => 'Student', 'email' => 'student-note@example.test', 'password' => 'long-password-here']);
        $staff = User::create(['name' => 'Staff', 'email' => 'staff-note@example.test', 'password' => 'long-password-here']);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $ownClass = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $otherClass = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 2']);
        $arm = ClassArm::create(['class_id' => $ownClass->id, 'name' => 'A']);
        Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $arm->id, 'admission_date' => today()]);
        $subject = Subject::create(['name' => 'Mathematics']);
        Storage::fake('local');
        Storage::disk('local')->put('enotes/own.pdf', 'Own file');
        Storage::disk('local')->put('enotes/other.pdf', 'Other file');
        $own = ENote::create(['class_id' => $ownClass->id, 'subject_id' => $subject->id,
            'title' => 'Own class note', 'file_path' => 'enotes/own.pdf', 'uploaded_by' => $staff->id]);
        $other = ENote::create(['class_id' => $otherClass->id, 'subject_id' => $subject->id,
            'title' => 'Other class note', 'file_path' => 'enotes/other.pdf', 'uploaded_by' => $staff->id]);

        $this->actingAs($studentUser)->get(route('enotes.mine'))->assertOk()
            ->assertSee('Own class note')->assertDontSee('Other class note');
        $this->get(route('enotes.mine.download', $own))->assertOk();
        $this->get(route('enotes.mine.download', $other))->assertNotFound();
    }
}
