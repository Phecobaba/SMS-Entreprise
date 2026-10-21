<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

class StudentDirectory
{
    public function count(?User $viewer = null, ?int $sectionId = null): int
    {
        $query = Student::active();
        if ($viewer) {
            app(SectionAccess::class)->scopeStudents($query, $viewer, $sectionId);
        }

        return $query->count();
    }

    public function countForArm(int $armId): int
    {
        return Student::active()->where('class_arm_id', $armId)->count();
    }

    public function historyCountForArm(int $armId): int
    {
        return Student::where('class_arm_id', $armId)->count();
    }

    public function candidateCountForLevel(int $levelId): int
    {
        return Candidate::where('applied_level_id', $levelId)->count();
    }

    public function rosterForArm(int $armId)
    {
        return Student::active()->with('user')->where('class_arm_id', $armId)->orderBy('id')->get();
    }

    public function dashboardRosterForArm(int $armId)
    {
        return Student::active()->with('user')->where('class_arm_id', $armId)->orderBy('id')->limit(8)->get();
    }

    public function countsByArmIds(array $armIds): array
    {
        return Student::active()->whereIn('class_arm_id', $armIds)
            ->selectRaw('class_arm_id, count(*) as student_count')
            ->groupBy('class_arm_id')->pluck('student_count', 'class_arm_id')->all();
    }

    public function idsForArms(array $armIds): array
    {
        return Student::whereIn('class_arm_id', $armIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function idsReferencingArms(array $armIds): array
    {
        return Student::where(fn ($query) => $query->whereIn('class_arm_id', $armIds)
            ->orWhereIn('awaiting_from_class_arm_id', $armIds))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function activeIdsForArms(array $armIds): array
    {
        return Student::active()->whereIn('class_arm_id', $armIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function activeForArms(array $armIds, ?int $armId = null, int $perPage = 20)
    {
        return Student::active()->with(['user', 'classArm.schoolClass'])
            ->whereIn('class_arm_id', $armIds)
            ->when($armId, fn ($query) => $query->where('class_arm_id', $armId))
            ->orderBy('id')->paginate($perPage)->withQueryString();
    }

    public function isActive(int $studentId): bool
    {
        return Student::active()->whereKey($studentId)->exists();
    }

    public function status(int $studentId): ?string
    {
        return Student::whereKey($studentId)->value('status');
    }

    public function forUser(User $user): ?Student
    {
        return Student::where('user_id', $user->id)->first();
    }

    public function forActiveUser(User $user): ?Student
    {
        return Student::active()->where('user_id', $user->id)->first();
    }

    public function forPortalUser(User $user): ?Student
    {
        return Student::with(['user', 'classArm.schoolClass.level.section'])
            ->where('user_id', $user->id)->first();
    }

    public function findForPortal(int $studentId): Student
    {
        return Student::with('user')->findOrFail($studentId);
    }

    public function portalStudents(array $studentIds)
    {
        return Student::with(['user', 'classArm.schoolClass.level.section'])
            ->whereIn('id', $studentIds)->orderBy('id')->get();
    }

    public function belongsToUser(int $studentId, User $user): bool
    {
        return Student::whereKey($studentId)->where('user_id', $user->id)->exists();
    }

    public function classArmId(int $studentId): int
    {
        $student = Student::find($studentId);
        if (! $student) {
            throw ValidationException::withMessages(['student_id' => 'Choose a student in this school.']);
        }

        return $student->class_arm_id;
    }

    public function roster(?array $armIds = null, ?User $viewer = null)
    {
        $query = Student::active()->with('user');
        if ($viewer) {
            app(SectionAccess::class)->scopeStudents($query, $viewer);
        }
        if ($armIds !== null) {
            $query->whereIn('class_arm_id', $armIds);
        }

        return $query->orderBy('id')->paginate(20, ['*'], 'students_page');
    }

    public function names(array $studentIds): array
    {
        return Student::with('user')->whereIn('id', $studentIds)->get()
            ->mapWithKeys(fn (Student $student) => [$student->id => $student->user?->name ?? 'Student #'.$student->id])->all();
    }

    public function userIds(array $studentIds): array
    {
        return Student::whereIn('id', $studentIds)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }
}
