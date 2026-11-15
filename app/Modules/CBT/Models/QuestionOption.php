<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model
{
    use BelongsToTenant;

    protected $fillable = ['question_id', 'text', 'is_correct', 'marks'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'marks' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (QuestionOption $option): void {
            if (! $option->is_correct) {
                $option->marks = 0;

                return;
            }
            if (! $option->isDirty('marks') || (int) $option->marks < 1) {
                $legacyMarks = (float) Question::findOrFail($option->question_id)->marks;
                $option->marks = max(1, (int) round($legacyMarks));
            }
        });
    }
}
