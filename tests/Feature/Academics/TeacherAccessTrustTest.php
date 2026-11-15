<?php

namespace Tests\Feature\Academics;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherAccessTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_pair_pickers_labels_and_server_scope_include_only_assigned_pairs(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $roles = app(DefaultRoleCatalog::class)->provision();
        $teacher = User::create(['name' => 'Pair Teacher', 'email' => 'pair-teacher@example.test', 'password' => 'long-password-here', 'status' => 'active']);
        $teacher->roles()->attach($roles['Teacher'], ['school_id' => $school->id]);

        $section = Section::create(['name' => 'Secondary School', 'head_title' => 'Principal']);
        $level = AcademicLevel::create(['section_id' => $section->id, 'name' => 'Junior Secondary']);
        $jsOne = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS1']);
        $jsTwo = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS2']);
        $jsThree = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JS3']);
        ClassArm::create(['class_id' => $jsOne->id, 'name' => 'Gold', 'form_teacher_user_id' => $teacher->id]);
        ClassArm::create(['class_id' => $jsTwo->id, 'name' => 'Diamond', 'form_teacher_user_id' => $teacher->id]);
        $jsThreeArm = ClassArm::create(['class_id' => $jsThree->id, 'name' => 'A']);
        $math = Subject::create(['section_id' => $section->id, 'name' => 'Mathematics']);
        $english = Subject::create(['section_id' => $section->id, 'name' => 'English']);
        $science = Subject::create(['section_id' => $section->id, 'name' => 'Science']);
        foreach ([[$jsOne, $math], [$jsTwo, $english], [$jsThree, $science]] as [$class, $subject]) {
            ClassSubject::create(['class_id' => $class->id, 'subject_id' => $subject->id]);
        }
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $jsOne->id, 'subject_id' => $math->id]);
        TeacherSubjectAssignment::create(['user_id' => $teacher->id, 'class_id' => $jsTwo->id, 'subject_id' => $english->id]);

        foreach ([route('enotes.index'), route('assignments.index'), route('dashboard')] as $url) {
            $this->actingAs($teacher)->get($url)->assertOk()
                ->assertSee('Mathematics · JS1')
                ->assertSee('English · JS2')
                ->assertDontSee('Science · JS3');
        }
        $this->get(route('dashboard'))->assertSee('JS1 Gold')->assertSee('JS2 Diamond');

        Storage::fake('local');
        $this->post(route('enotes.store'), [
            'pair' => $jsThree->id.':'.$science->id,
            'title' => 'Forged note',
            'file' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('subject_id');
        $this->post(route('assignments.store'), [
            'pair' => $jsThree->id.':'.$science->id,
            'class_arm_id' => $jsThreeArm->id,
            'title' => 'Forged assignment',
            'due_date' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrors('subject_id');
        $this->assertDatabaseMissing('enotes', ['title' => 'Forged note']);
        $this->assertDatabaseMissing('assignments', ['title' => 'Forged assignment']);
    }
}
