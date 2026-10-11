<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\SchoolUserDirectory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FormTeacherAssignments
{
    public function dashboardArm(User $actor): ?ClassArm
    {
        return ClassArm::with('schoolClass')->where('form_teacher_user_id', $actor->id)->orderBy('id')->first();
    }

    public function assign(int $armId, int $userId): ClassArm
    {
        $arm = ClassArm::findOrFail($armId);
        $user = app(SchoolUserDirectory::class)->find($userId);
        if (! $user || ! Gate::forUser($user)->allows('mark_attendance')) {
            throw ValidationException::withMessages(['user_id' => 'Choose a user with attendance-marking permission.']);
        }
        $arm->update(['form_teacher_user_id' => $user->id]);

        return $arm;
    }

    public function assertMayMark(ClassArm $arm, User $actor): void
    {
        Gate::forUser($actor)->authorize('mark_attendance');
        if (Gate::forUser($actor)->allows('manage_attendance')) {
            app(SectionAccess::class)->assertClassArm($actor, $arm->id);
        } elseif ($arm->form_teacher_user_id !== $actor->id) {
            throw ValidationException::withMessages(['class_arm_id' => 'You are not this class arm’s form teacher.']);
        }
    }

    public function isFormTeacher(int $armId, User $actor): bool
    {
        return ClassArm::whereKey($armId)->where('form_teacher_user_id', $actor->id)->exists();
    }

    public function armsFor(User $actor)
    {
        $query = ClassArm::with('schoolClass');
        if (Gate::forUser($actor)->allows('manage_attendance')) {
            app(SectionAccess::class)->scopeArms($query, $actor);
        } else {
            $query->where('form_teacher_user_id', $actor->id);
        }

        return $query->orderBy('id')->paginate(20);
    }
}
