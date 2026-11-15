<?php

namespace App\Modules\CBT\Models;

use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResitGrant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'exam_id', 'student_id', 'granted_by', 'reason', 'opens_until',
        'used_attempt_id', 'withdrawn_at',
    ];

    protected function casts(): array
    {
        return ['opens_until' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function usedAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamSubmission::class, 'used_attempt_id');
    }
}
