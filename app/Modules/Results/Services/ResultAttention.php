<?php

namespace App\Modules\Results\Services;

use App\Modules\Core\Models\User;
use App\Modules\Results\Models\Result;

class ResultAttention
{
    /** @var array<int, array{count: int, url: string, text: string}|null> */
    private array $cache = [];

    /** @return array{count: int, url: string, text: string}|null */
    public function for(User $actor): ?array
    {
        if (array_key_exists($actor->id, $this->cache)) {
            return $this->cache[$actor->id];
        }

        if ($actor->can('approve_results')) {
            return $this->cache[$actor->id] = $this->submitted($actor);
        }

        if ($actor->can('enter_results')) {
            return $this->cache[$actor->id] = $this->rejected($actor);
        }

        return $this->cache[$actor->id] = null;
    }

    /** @return array{count: int, url: string, text: string}|null */
    private function submitted(User $actor): ?array
    {
        $query = Result::query()->where('status', 'Submitted');
        if (! $actor->can('view_all_sections')) {
            $query->whereHas(
                'classArm.schoolClass.level.section.heads',
                fn ($heads) => $heads->whereKey($actor->id),
            );
        }

        $count = $query->count();
        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            'url' => route('results.index', absolute: false),
            'text' => $count.' submitted '.str('result')->plural($count).' waiting for approval',
        ];
    }

    /** @return array{count: int, url: string, text: string}|null */
    private function rejected(User $actor): ?array
    {
        $summary = Result::query()
            ->join('class_arms', function ($join): void {
                $join->on('class_arms.id', '=', 'results.class_arm_id')
                    ->on('class_arms.school_id', '=', 'results.school_id');
            })
            ->join('teacher_subject_assignments as result_teacher_assignments', function ($join) use ($actor): void {
                $join->on('result_teacher_assignments.class_id', '=', 'class_arms.class_id')
                    ->on('result_teacher_assignments.subject_id', '=', 'results.subject_id')
                    ->on('result_teacher_assignments.school_id', '=', 'results.school_id')
                    ->where('result_teacher_assignments.user_id', '=', $actor->id);
            })
            ->where('results.status', 'Rejected')
            ->selectRaw('COUNT(DISTINCT results.id) as aggregate, MIN(results.id) as first_result_id')
            ->first();

        $count = (int) ($summary?->aggregate ?? 0);
        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            'url' => $count === 1
                ? route('results.show', (int) $summary->first_result_id, false)
                : route('results.index', ['status' => 'Rejected'], false),
            'text' => $count.' '.str('result')->plural($count).' returned for correction',
        ];
    }
}
