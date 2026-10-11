<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class SectionAccess
{
    public function sectionIds(User $actor): array
    {
        $query = Section::query();
        if (! $actor->can('view_all_sections')) {
            $query->where('head_user_id', $actor->id);
        }

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function scopeLevels(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $ids = $this->allowedIds($actor, $requestedSectionId);

        return $ids === null ? $query : $query->whereIn('section_id', $ids);
    }

    public function scopeClasses(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $ids = $this->allowedIds($actor, $requestedSectionId);

        return $ids === null ? $query : $query->whereHas('level', fn (Builder $level) => $level->whereIn('section_id', $ids));
    }

    public function scopeArms(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $ids = $this->allowedIds($actor, $requestedSectionId);

        return $ids === null ? $query : $query->whereHas(
            'schoolClass.level',
            fn (Builder $level) => $level->whereIn('section_id', $ids),
        );
    }

    public function scopeStudents(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $armIds = $this->visibleArmIds($actor, $requestedSectionId);

        return $armIds === null ? $query : $query->whereIn('class_arm_id', $armIds);
    }

    public function scopeResults(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $armIds = $this->visibleArmIds($actor, $requestedSectionId);

        return $armIds === null ? $query : $query->whereIn('class_arm_id', $armIds);
    }

    public function scopeCards(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $armIds = $this->visibleArmIds($actor, $requestedSectionId);

        return $armIds === null ? $query : $query->whereIn('class_arm_id', $armIds);
    }

    public function scopeCandidates(Builder $query, User $actor, ?int $requestedSectionId = null): Builder
    {
        $ids = $this->allowedIds($actor, $requestedSectionId);

        return $ids === null ? $query : $query->whereHas(
            'appliedLevel',
            fn (Builder $level) => $level->whereIn('section_id', $ids),
        );
    }

    public function visibleArmIds(User $actor, ?int $requestedSectionId = null): ?array
    {
        $ids = $this->allowedIds($actor, $requestedSectionId);
        if ($ids === null) {
            return null;
        }

        return ClassArm::whereHas('schoolClass.level', fn (Builder $level) => $level->whereIn('section_id', $ids))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function assertClassArm(User $actor, int $classArmId): void
    {
        $armIds = $this->visibleArmIds($actor);
        if ($armIds !== null && ! in_array($classArmId, $armIds, true)) {
            throw new AuthorizationException('You cannot access another section’s data.');
        }
    }

    public function assertClass(User $actor, int $classId): void
    {
        $query = SchoolClass::whereKey($classId);
        if (! $this->scopeClasses($query, $actor)->exists()) {
            throw new AuthorizationException('You cannot access another section’s class.');
        }
    }

    public function assertLevel(User $actor, int $levelId): void
    {
        $query = AcademicLevel::whereKey($levelId);
        if (! $this->scopeLevels($query, $actor)->exists()) {
            throw new AuthorizationException('You cannot access another section’s level.');
        }
    }

    public function assertSection(User $actor, int $sectionId): void
    {
        if (! Section::whereKey($sectionId)->exists()) {
            throw new AuthorizationException('That section does not exist in this school.');
        }
        $ids = $this->allowedIds($actor, null);
        if ($ids !== null && ! in_array($sectionId, $ids, true)) {
            throw new AuthorizationException('You cannot access another section’s data.');
        }
    }

    public function assertCandidate(User $actor, int $candidateId): void
    {
        $query = Candidate::whereKey($candidateId);
        if (! $this->scopeCandidates($query, $actor)->exists()) {
            throw new AuthorizationException('You cannot access another section’s admission record.');
        }
    }

    public function sectionForArm(int $classArmId): ?Section
    {
        return ClassArm::with('schoolClass.level.section')->find($classArmId)?->schoolClass?->level?->section;
    }

    private function allowedIds(User $actor, ?int $requestedSectionId): ?array
    {
        // Existing deployments can configure Sections after this migration. Until the first
        // Section exists, retain legacy access rather than making every existing record vanish.
        if (! Section::exists()) {
            return null;
        }

        if ($actor->can('view_all_sections')) {
            if ($requestedSectionId === null) {
                return null;
            }
            if (! Section::whereKey($requestedSectionId)->exists()) {
                throw new AuthorizationException('You cannot switch to that section.');
            }

            return [$requestedSectionId];
        }

        $ids = $this->sectionIds($actor);
        if ($requestedSectionId === null) {
            return $ids;
        }
        if (! in_array($requestedSectionId, $ids, true)) {
            throw new AuthorizationException('You cannot switch to that section.');
        }

        return [$requestedSectionId];
    }
}
