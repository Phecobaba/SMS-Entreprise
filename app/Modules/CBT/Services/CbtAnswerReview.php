<?php

namespace App\Modules\CBT\Services;

use App\Modules\CBT\Models\ExamAnswer;
use App\Modules\CBT\Models\Question;

class CbtAnswerReview
{
    public function forQuestion(Question $question, ?ExamAnswer $answer, bool $showCorrect = true): array
    {
        $selectedIds = collect($answer?->selectedOptionIds() ?? []);
        $correct = $question->options->where('is_correct', true);
        $earned = (int) $correct->whereIn('id', $selectedIds)->sum('marks');
        $available = (int) $correct->sum('marks');
        $selected = $question->options->whereIn('id', $selectedIds);

        return [
            'question' => $question->text,
            'position' => $question->position,
            'selected' => $selected->pluck('text')->values()->all(),
            'correct' => $showCorrect ? $correct->pluck('text')->values()->all() : null,
            'earned' => $earned,
            'available' => $available,
            'was_correct' => $showCorrect ? $earned === $available && $selected->count() === $correct->count() : null,
            'options' => $question->options->map(fn ($option): array => [
                'id' => $option->id,
                'text' => $option->text,
                'selected' => $selectedIds->contains($option->id),
                'correct' => $showCorrect ? (bool) $option->is_correct : null,
                'marks' => $showCorrect && $option->is_correct ? (int) $option->marks : null,
            ])->values()->all(),
        ];
    }
}
