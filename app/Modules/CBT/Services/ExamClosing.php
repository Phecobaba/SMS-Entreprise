<?php

namespace App\Modules\CBT\Services;

use App\Modules\CBT\Models\Exam;
use Illuminate\Database\Eloquent\Builder;

class ExamClosing
{
    public function countsAsClosed(Exam $exam): bool
    {
        $closed = $exam->status === 'Closed'
            || ($exam->status === 'Published'
                && $exam->closes_automatically
                && $exam->closes_at !== null
                && now()->gte($exam->closes_at));

        return $closed;
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where(function (Builder $closed): void {
            $closed->where('status', 'Closed')
                ->orWhere(function (Builder $automatic): void {
                    $automatic->where('status', 'Published')
                        ->where('closes_automatically', true)
                        ->whereNotNull('closes_at')
                        ->where('closes_at', '<=', now());
                });
        });
    }

    public function displayStatus(Exam $exam): string
    {
        return $this->countsAsClosed($exam) ? 'Closed' : $exam->status;
    }
}
