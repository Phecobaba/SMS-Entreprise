<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswerSelection extends Model
{
    use BelongsToTenant;

    protected $fillable = ['exam_answer_id', 'question_option_id'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(ExamAnswer::class, 'exam_answer_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }
}
