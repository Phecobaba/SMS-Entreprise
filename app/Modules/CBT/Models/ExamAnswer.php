<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAnswer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['exam_submission_id', 'question_id', 'selected_option_id', 'flagged'];

    protected function casts(): array
    {
        return ['flagged' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(ExamAnswerSelection::class, 'exam_answer_id');
    }

    public function selectedOptionIds(): array
    {
        if ($this->relationLoaded('selections') && $this->selections->isNotEmpty()) {
            return $this->selections->pluck('question_option_id')->map(fn ($id): int => (int) $id)->all();
        }

        return $this->selected_option_id ? [(int) $this->selected_option_id] : [];
    }
}
