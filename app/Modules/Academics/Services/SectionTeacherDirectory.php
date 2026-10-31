<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SectionTeacherDirectory
{
    public const SUBJECT_PERMISSIONS = ['enter_results', 'mark_assignments', 'upload_enotes'];

    public const FORM_PERMISSION = ['mark_attendance'];

    public function belongsTo(User $user, int $sectionId): bool
    {
        return in_array($sectionId, $this->sectionIds($user), true);
    }

    public function sectionIds(User $user): array
    {
        $direct = $user->sections()->pluck('sections.id');
        $classIds = TeacherSubjectAssignment::where('user_id', $user->id)->select('class_id');
        $subjectSections = SchoolClass::whereIn('id', $classIds)->whereHas('level', fn (Builder $query) => $query->whereNotNull('section_id'))
            ->with('level:id,section_id')->get()->pluck('level.section_id');
        $formClassIds = ClassArm::where('form_teacher_user_id', $user->id)->select('class_id');
        $formSections = SchoolClass::whereIn('id', $formClassIds)->whereHas('level', fn (Builder $query) => $query->whereNotNull('section_id'))
            ->with('level:id,section_id')->get()->pluck('level.section_id');

        return $direct->merge($subjectSections)->merge($formSections)->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    public function candidates(int $sectionId, array $permissions, bool $includeOtherTeachers): array
    {
        $eligible = User::where('status', 'active')
            ->whereHas('roles.permissions', fn (Builder $query) => $query->whereIn('permissions.name', $permissions))
            ->orderBy('name')->limit(200)->get(['id', 'name', 'email', 'status']);
        $inSectionIds = $this->teacherIdsForSection($sectionId);
        $inSection = $eligible->filter(fn (User $user): bool => $inSectionIds->contains($user->id))->values();

        return [
            'section' => $inSection,
            'other' => $includeOtherTeachers ? $eligible->reject(fn (User $user): bool => $inSectionIds->contains($user->id))->values() : collect(),
            'all' => $includeOtherTeachers ? $inSection->concat($eligible->reject(fn (User $user): bool => $inSectionIds->contains($user->id)))->values() : $inSection,
        ];
    }

    public function isEligible(User $user, int $sectionId, array $permissions, bool $allowOtherTeacher): bool
    {
        if ($user->status !== 'active' || ! $user->roles()->whereHas('permissions', fn (Builder $query) => $query->whereIn('permissions.name', $permissions))->exists()) {
            return false;
        }

        return $allowOtherTeacher || $this->belongsTo($user, $sectionId);
    }

    private function teacherIdsForSection(int $sectionId): Collection
    {
        $classIds = SchoolClass::whereHas('level', fn (Builder $query) => $query->where('section_id', $sectionId))->select('id');

        return User::whereHas('sections', fn (Builder $query) => $query->whereKey($sectionId))->pluck('id')
            ->merge(TeacherSubjectAssignment::whereIn('class_id', $classIds)->pluck('user_id'))
            ->merge(ClassArm::whereIn('class_id', $classIds)->whereNotNull('form_teacher_user_id')->pluck('form_teacher_user_id'))
            ->map(fn ($id): int => (int) $id)->unique()->values();
    }
}
