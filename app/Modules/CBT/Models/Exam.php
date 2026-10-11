<?php

namespace App\Modules\CBT\Models;

use App\Modules\Academics\Models\Subject;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use BelongsToTenant;

    protected $fillable = ['exam_type_id', 'subject_id', 'class_id', 'term_id', 'assessment_component_id', 'created_by', 'title', 'duration', 'scheduled_at', 'closes_at', 'status', 'pass_mark', 'result_release_type', 'result_release_at', 'is_released', 'show_score', 'show_correct_answers', 'show_explanations', 'requires_approval', 'approved_for_release_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'closes_at' => 'datetime', 'result_release_at' => 'datetime', 'approved_for_release_at' => 'datetime', 'is_released' => 'boolean', 'show_score' => 'boolean', 'show_correct_answers' => 'boolean', 'show_explanations' => 'boolean', 'requires_approval' => 'boolean'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
