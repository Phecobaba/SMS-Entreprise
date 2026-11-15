<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_reference', 'child_first_name', 'child_surname', 'child_other_names', 'date_of_birth',
        'gender', 'applied_level_id', 'previous_school', 'guardian_name', 'guardian_relationship',
        'guardian_phone', 'guardian_email', 'guardian_address', 'username', 'active_username_key',
        'status', 'fee_amount_kobo', 'currency', 'access_token_hash', 'consented_at', 'consent_version',
        'expires_at', 'registered_at', 'last_checked_at', 'confirmed_attempt_id',
        'submission_email_sent_at', 'confirmation_email_sent_at', 'mail_failed_at', 'needs_attention',
        'exam_session_id', 'exam_notified_at',
        'assessment_outcome', 'assessment_note', 'assessment_recorded_by', 'assessment_recorded_at',
    ];

    protected $hidden = ['access_token_hash'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', 'consented_at' => 'datetime', 'expires_at' => 'datetime',
            'registered_at' => 'datetime', 'last_checked_at' => 'datetime',
            'submission_email_sent_at' => 'datetime', 'confirmation_email_sent_at' => 'datetime',
            'mail_failed_at' => 'datetime', 'exam_notified_at' => 'datetime',
            'assessment_recorded_at' => 'datetime',
            'needs_attention' => 'boolean', 'fee_amount_kobo' => 'integer',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'applied_level_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ApplicationPaymentAttempt::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function assessmentRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessment_recorded_by');
    }
}
