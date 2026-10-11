<?php

namespace App\Modules\Assignments\Services;

use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\User;

class ParentAccountDirectory
{
    public function existsForUser(int $userId): bool
    {
        return ParentProfile::where('user_id', $userId)->exists();
    }

    public function isWard(int $studentId, int $parentUserId): bool
    {
        $parentId = ParentProfile::where('user_id', $parentUserId)->value('id');

        return $parentId && ParentStudent::where('parent_id', $parentId)->where('student_id', $studentId)->exists();
    }

    public function wardIds(User $user): array
    {
        $parentId = ParentProfile::where('user_id', $user->id)->value('id');

        return $parentId
            ? ParentStudent::where('parent_id', $parentId)->orderBy('id')->pluck('student_id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    public function userIdsForStudents(array $studentIds): array
    {
        $parentIds = ParentStudent::whereIn('student_id', $studentIds)->pluck('parent_id');

        return ParentProfile::whereIn('id', $parentIds)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }
}
