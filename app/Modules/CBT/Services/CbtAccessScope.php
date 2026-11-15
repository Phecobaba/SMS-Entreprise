<?php

namespace App\Modules\CBT\Services;

use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\CBT\Models\Exam;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CbtAccessScope
{
    private array $managedExams = [];

    public function managementQuery(User $actor): Builder
    {
        $query = Exam::query();
        if ($actor->can('view_all_sections')) {
            return $query;
        }

        $sectionIds = app(SectionAccess::class)->sectionIds($actor);

        return $query->whereNotNull('class_id')->whereHas(
            'schoolClass.level',
            fn (Builder $level) => $level->whereIn('section_id', $sectionIds),
        );
    }

    public function managedExam(User $actor, int $examId): Exam
    {
        $key = spl_object_id(request()).':'.$actor->id.':'.$examId;

        return $this->managedExams[$key] ??= $this->managementQuery($actor)->findOrFail($examId);
    }

    public function classes(User $actor): Collection
    {
        return app(SectionAccess::class)->scopeClasses(SchoolClass::query(), $actor)
            ->with('level.section')->orderBy('name')->get();
    }

    public function assertClass(User $actor, int $classId): void
    {
        app(SectionAccess::class)->scopeClasses(SchoolClass::whereKey($classId), $actor)->firstOrFail();
    }

    public function mayManageClassless(User $actor): bool
    {
        return $actor->can('view_all_sections');
    }

    public function resultsQuery(User $actor): Builder
    {
        if ($actor->can('view_all_sections')) {
            return Exam::query();
        }

        if ($actor->can('manage_cbt')) {
            return $this->managementQuery($actor);
        }

        $pairs = app(ResultTeachingAssignments::class)->pairsFor($actor);
        $query = app(ExamClosing::class)->scopeClosed(Exam::query());
        $query->where(function (Builder $scope) use ($pairs): void {
            foreach ($pairs as $pair) {
                $scope->orWhere(function (Builder $item) use ($pair): void {
                    $item->where('class_id', $pair['class_id'])->where('subject_id', $pair['subject_id']);
                });
            }
            if ($pairs === []) {
                $scope->whereRaw('1 = 0');
            }
        });

        return $query;
    }

    public function resultExam(User $actor, int $examId): Exam
    {
        return $this->resultsQuery($actor)->findOrFail($examId);
    }
}
