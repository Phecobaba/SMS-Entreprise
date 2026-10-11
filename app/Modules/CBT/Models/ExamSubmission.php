<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSubmission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['exam_id', 'candidate_id', 'student_id', 'started_at', 'submitted_at', 'raw_score', 'max_score', 'converted_score', 'status'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'raw_score' => 'decimal:2', 'max_score' => 'decimal:2', 'converted_score' => 'decimal:2'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
