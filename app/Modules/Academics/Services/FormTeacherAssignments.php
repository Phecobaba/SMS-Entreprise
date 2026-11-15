<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FormTeacherAssignments
{
    public function dashboardArms(User $actor)
    {
        return ClassArm::with('schoolClass')->where('form_teacher_user_id', $actor->id)->orderBy('id')->get();
    }

    public function assign(int $armId, int $userId, User $actor): ClassArm
    {
        $arm = ClassArm::findOrFail($armId);
        $user = User::find($userId);
        $section = app(SectionAccess::class)->sectionForArm($arm->id);
        $allowOtherTeacher = $actor->can('view_all_sections');
        if (! $user || ! $section || ! app(SectionTeacherDirectory::class)->isEligible($user, $section->id, SectionTeacherDirectory::FORM_PERMISSION, $allowOtherTeacher)) {
            throw ValidationException::withMessages(['user_id' => 'Choose an active attendance teacher who belongs to this section.']);
        }
        $previousTeacherId = $arm->form_teacher_user_id;
        DB::transaction(function () use ($arm, $user, $actor, $previousTeacherId): void {
            $arm->update(['form_teacher_user_id' => $user->id]);
            app(AuditRecorder::class)->record($actor, 'form_teacher_assigned', $arm, [
                'class_arm_id' => $arm->id,
                'previous_form_teacher_user_id' => $previousTeacherId,
                'form_teacher_user_id' => $user->id,
            ]);
        });

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
        $query = ClassArm::with(['schoolClass.level.section', 'formTeacher']);
        if (Gate::forUser($actor)->allows('manage_attendance')) {
            app(SectionAccess::class)->scopeArms($query, $actor);
        } else {
            $query->where('form_teacher_user_id', $actor->id);
        }

        return $query->orderBy('id')->paginate(20);
    }
}
