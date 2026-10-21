<?php

namespace Tests\Feature\Portals;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParentDashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_dashboard_query_count_is_constant_as_ward_count_grows(): void
    {
        $school = School::create(['name' => 'Oakfield']);
        config()->set('tenancy.default_school_id', $school->id);
        app(TenantContext::class)->set($school->id);
        $parent = User::create([
            'name' => 'Parent',
            'email' => 'parent-query-count@example.test',
            'password' => 'long-password-here',
        ]);
        $profile = ParentProfile::create(['user_id' => $parent->id]);
        $level = AcademicLevel::create(['name' => 'Secondary']);
        $class = SchoolClass::create(['academic_level_id' => $level->id, 'name' => 'JSS 1']);
        $arm = ClassArm::create(['class_id' => $class->id, 'name' => 'A']);

        $this->createWard($profile, $arm, 1);
        $oneWardQueries = $this->dashboardQueryCount($parent);

        $this->createWard($profile, $arm, 2);
        $this->createWard($profile, $arm, 3);
        $threeWardQueries = $this->dashboardQueryCount($parent);

        $this->assertLessThanOrEqual(
            2,
            abs($threeWardQueries - $oneWardQueries),
            "Expected a constant query count; one ward used {$oneWardQueries} queries and three wards used {$threeWardQueries}.",
        );
    }

    private function createWard(ParentProfile $profile, ClassArm $arm, int $number): Student
    {
        app(TenantContext::class)->set($profile->school_id);
        $user = User::create([
            'name' => 'Ward '.$number,
            'email' => "ward-{$number}-query-count@example.test",
            'password' => 'long-password-here',
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'class_arm_id' => $arm->id,
            'admission_date' => today(),
        ]);
        ParentStudent::create(['parent_id' => $profile->id, 'student_id' => $student->id]);

        return $student;
    }

    private function dashboardQueryCount(User $parent): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($parent)->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
