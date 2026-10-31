<?php

namespace Tests\Feature\Academics;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\Assignment;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamType;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\ENotes\Models\ENote;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SubjectOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_splits_shared_subjects_preserves_history_copies_unused_and_is_idempotent(): void
    {
        $f = $this->fixture();
        $shared = Subject::create(['section_id' => $f['primary']->id, 'name' => 'Mathematics']);
        $unused = Subject::create(['section_id' => $f['primary']->id, 'name' => 'Library Skills']);
        ClassSubject::create(['class_id' => $f['primaryClass']->id, 'subject_id' => $shared->id]);
        ClassSubject::create(['class_id' => $f['secondaryClass']->id, 'subject_id' => $shared->id]);
        TeacherSubjectAssignment::create(['user_id' => $f['teacher']->id, 'class_id' => $f['secondaryClass']->id, 'subject_id' => $shared->id]);
        $component = AssessmentComponent::create(['section_id' => $f['secondary']->id, 'name' => 'Exam', 'max_score' => 100]);
        $scheme = GradingScheme::create(['section_id' => $f['secondary']->id, 'name' => 'Default']);
        $template = ResultTemplate::create(['section_id' => $f['secondary']->id, 'academic_level_id' => $f['secondaryLevel']->id, 'grading_scheme_id' => $scheme->id,
            'name' => 'Secondary', 'scope_key' => 'level:'.$f['secondaryLevel']->id]);
        $studentUser = User::create(['name' => 'Learner', 'email' => 'subject-learner@example.test', 'password' => 'long-password-here']);
        $student = Student::create(['user_id' => $studentUser->id, 'class_arm_id' => $f['secondaryArm']->id, 'admission_date' => today()]);
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31']);
        $term = AcademicTerm::create(['session_id' => $session->id, 'name' => 'First Term', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18']);
        $result = Result::create(['student_id' => $student->id, 'class_arm_id' => $f['secondaryArm']->id, 'subject_id' => $shared->id,
            'term_id' => $term->id, 'result_template_id' => $template->id, 'status' => 'Draft', 'total_score' => 72, 'percentage' => 72]);
        $item = ResultItem::create(['result_id' => $result->id, 'assessment_component_id' => $component->id, 'score' => 72]);
        $examType = ExamType::create(['name' => 'Internal']);
        $exam = Exam::create(['exam_type_id' => $examType->id, 'class_id' => $f['secondaryClass']->id, 'subject_id' => $shared->id,
            'term_id' => $term->id, 'assessment_component_id' => $component->id, 'created_by' => $f['admin']->id,
            'title' => 'Mathematics', 'duration' => 60, 'status' => 'Draft', 'result_release_type' => 'Manual']);
        $assignment = Assignment::create(['subject_id' => $shared->id, 'class_arm_id' => $f['secondaryArm']->id,
            'title' => 'Homework', 'due_date' => today(), 'created_by' => $f['teacher']->id]);
        $note = ENote::create(['subject_id' => $shared->id, 'class_id' => $f['secondaryClass']->id,
            'title' => 'Lesson note', 'uploaded_by' => $f['teacher']->id]);

        $before = [Subject::count(), ClassSubject::count(), TeacherSubjectAssignment::count(), Result::count(), $item->score];
        $this->assertSame(0, Artisan::call('oakfield:migrate-subject-sections', ['--dry-run' => true]));
        $this->assertStringContainsString('DRY RUN', Artisan::output());
        $this->assertSame($before, [Subject::count(), ClassSubject::count(), TeacherSubjectAssignment::count(), Result::count(), $item->fresh()->score]);

        $this->assertSame(0, Artisan::call('oakfield:migrate-subject-sections', ['--actor' => $f['admin']->id]));
        $secondaryMath = Subject::where('section_id', $f['secondary']->id)->where('name', 'Mathematics')->firstOrFail();
        $this->assertSame(2, Subject::where('name', 'Mathematics')->count());
        $this->assertSame(2, Subject::where('name', $unused->name)->count());
        foreach (ClassSubject::with(['subject', 'schoolClass.level'])->get() as $classSubject) {
            $this->assertSame($classSubject->schoolClass->level->section_id, $classSubject->subject->section_id);
        }
        $this->assertSame(1, Result::count());
        $this->assertSame('72.00', $result->fresh()->total_score);
        $this->assertSame('72.00', $item->fresh()->score);
        $this->assertSame($secondaryMath->id, $result->fresh()->subject_id);
        $this->assertSame($secondaryMath->id, $exam->fresh()->subject_id);
        $this->assertSame($secondaryMath->id, $assignment->fresh()->subject_id);
        $this->assertSame($secondaryMath->id, $note->fresh()->subject_id);
        $this->assertDatabaseHas('teacher_subject_assignments', [
            'user_id' => $f['teacher']->id, 'class_id' => $f['secondaryClass']->id, 'subject_id' => $secondaryMath->id,
        ]);

        $snapshot = [Subject::count(), ClassSubject::count(), TeacherSubjectAssignment::count(), Result::count(), $item->fresh()->score];
        $this->assertSame(0, Artisan::call('oakfield:migrate-subject-sections', ['--actor' => $f['admin']->id]));
        $this->assertSame($snapshot, [Subject::count(), ClassSubject::count(), TeacherSubjectAssignment::count(), Result::count(), $item->fresh()->score]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subject_sections_migrated', 'user_id' => $f['admin']->id]);
    }

    public function test_same_section_assignment_and_head_subject_scope_are_enforced(): void
    {
        $f = $this->fixture();
        $primarySubject = Subject::create(['section_id' => $f['primary']->id, 'name' => 'Mathematics']);
        $outside = Subject::create(['section_id' => $f['secondary']->id, 'name' => 'Physics']);
        $unassigned = User::create(['name' => 'Unassigned Head', 'email' => 'unassigned-subject-head@example.test', 'password' => 'long-password-here']);
        $unassigned->roles()->attach($f['head']->roles()->firstOrFail());
        $this->actingAs($unassigned)->get(route('academics.setup'))
            ->assertOk()->assertSee('No section assigned')->assertDontSee('Mathematics')->assertDontSee('Physics');

        $this->actingAs($f['head'])->post(route('academics.class-subjects.store'), [
            'class_id' => $f['primaryClass']->id, 'subject_id' => $outside->id,
        ])->assertForbidden();
        $this->post(route('academics.bulk-subjects.review'), [
            'section_id' => $f['primary']->id, 'level_id' => $f['primaryLevel']->id,
            'class_ids' => [$f['primaryClass']->id], 'subject_ids' => [$outside->id],
        ])->assertForbidden();
        $this->post(route('academics.teacher-assignments.review'), [
            'section_id' => $f['primary']->id, 'mode' => 'teacher', 'teacher_id' => $f['teacher']->id,
            'pairs' => [$f['primaryClass']->id.':'.$outside->id],
        ])->assertForbidden();
        $this->post(route('academics.subjects.store'), [
            'section_id' => $f['secondary']->id, 'name' => 'Chemistry',
        ])->assertForbidden();
        $this->put(route('academics.subjects.update', $outside), [
            'section_id' => $f['primary']->id, 'name' => 'Physics',
        ])->assertForbidden();
        $this->delete(route('academics.subjects.destroy', $outside))->assertForbidden();
        $this->put(route('academics.subjects.update', $primarySubject), [
            'section_id' => $f['secondary']->id, 'name' => 'Mathematics',
        ])->assertForbidden();
        $this->actingAs($f['admin'])->post(route('academics.class-subjects.store'), [
            'class_id' => $f['primaryClass']->id, 'subject_id' => $outside->id,
        ])->assertSessionHasErrors('subject_id');
    }

    public function test_subject_section_is_immutable_and_delete_and_unique_names_are_guarded_with_counts(): void
    {
        $f = $this->fixture();
        $subject = Subject::create(['section_id' => $f['primary']->id, 'name' => 'Mathematics']);
        ClassSubject::create(['class_id' => $f['primaryClass']->id, 'subject_id' => $subject->id]);
        TeacherSubjectAssignment::create(['user_id' => $f['teacher']->id, 'class_id' => $f['primaryClass']->id, 'subject_id' => $subject->id]);

        $this->actingAs($f['admin'])->put(route('academics.subjects.update', $subject), [
            'section_id' => $f['secondary']->id, 'name' => 'Mathematics',
        ])->assertForbidden();
        $this->assertSame($f['primary']->id, $subject->fresh()->section_id);
        $response = $this->delete(route('academics.subjects.destroy', $subject))->assertSessionHasErrors('subject');
        $message = $response->getSession()->get('errors')->first('subject');
        $this->assertStringContainsString('1 classes', $message);
        $this->assertStringContainsString('1 teachers', $message);

        $this->post(route('academics.subjects.store'), ['section_id' => $f['primary']->id, 'name' => 'Mathematics'])
            ->assertSessionHasErrors('name');
        $this->post(route('academics.subjects.store'), ['section_id' => $f['secondary']->id, 'name' => 'Mathematics'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'subject_created', 'user_id' => $f['admin']->id]);

        app(TenantContext::class)->set($f['school']->id);
        $empty = Subject::create(['section_id' => $f['primary']->id, 'name' => 'Library Skills']);
        $this->put(route('academics.subjects.update', $empty), [
            'section_id' => $f['secondary']->id, 'name' => 'Resource Centre',
        ])->assertForbidden();
        $this->assertDatabaseHas('subjects', [
            'id' => $empty->id, 'section_id' => $f['primary']->id, 'name' => 'Library Skills',
        ]);
        $this->put(route('academics.subjects.update', $empty), [
            'name' => 'Resource Centre',
        ])->assertSessionHasNoErrors();
        $this->delete(route('academics.subjects.destroy', $empty->fresh()))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('subjects', ['id' => $empty->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subject_updated', 'subject_id' => $empty->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subject_deleted', 'subject_id' => $empty->id]);
    }

    private function fixture(): array
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $admin = User::create(['name' => 'Admin', 'email' => 'subject-admin@example.test', 'password' => 'long-password-here']);
        $head = User::create(['name' => 'Head', 'email' => 'subject-head@example.test', 'password' => 'long-password-here']);
        $teacher = User::create(['name' => 'Teacher', 'email' => 'subject-teacher@example.test', 'password' => 'long-password-here']);
        $adminRole = Role::create(['name' => 'School Admin']);
        $headRole = Role::create(['name' => 'Principal']);
        foreach (['manage_academics', 'view_all_sections'] as $name) {
            $adminRole->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }
        $headRole->permissions()->attach([
            Permission::firstOrCreate(['name' => 'manage_section_subjects'])->id,
            Permission::firstOrCreate(['name' => 'manage_section_teacher_assignments'])->id,
        ]);
        $teacherRole = Role::create(['name' => 'Teacher']);
        $teacherRole->permissions()->attach(Permission::firstOrCreate(['name' => 'enter_results']));
        $admin->roles()->attach($adminRole);
        $head->roles()->attach($headRole);
        $teacher->roles()->attach($teacherRole);
        $primary = Section::create(['name' => 'Primary School', 'head_title' => 'Head Teacher']);
        $secondary = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $primary->heads()->attach($head, ['school_id' => $school->id]);
        $primary->heads()->attach($teacher, ['school_id' => $school->id]);
        $primaryLevel = AcademicLevel::create(['section_id' => $primary->id, 'name' => 'Primary']);
        $secondaryLevel = AcademicLevel::create(['section_id' => $secondary->id, 'name' => 'Secondary']);
        $primaryClass = SchoolClass::create(['academic_level_id' => $primaryLevel->id, 'name' => 'Primary 1']);
        $secondaryClass = SchoolClass::create(['academic_level_id' => $secondaryLevel->id, 'name' => 'JS1']);
        $primaryArm = ClassArm::create(['class_id' => $primaryClass->id, 'name' => 'Gold']);
        $secondaryArm = ClassArm::create(['class_id' => $secondaryClass->id, 'name' => 'Blue']);

        return compact('school', 'admin', 'head', 'teacher', 'primary', 'secondary', 'primaryLevel', 'secondaryLevel', 'primaryClass', 'secondaryClass', 'primaryArm', 'secondaryArm');
    }
}
