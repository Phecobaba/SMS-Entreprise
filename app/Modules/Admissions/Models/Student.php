<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_AWAITING = 'Awaiting admission';

    public const STATUS_GRADUATED = 'Graduated';

    protected $fillable = ['user_id', 'class_arm_id', 'candidate_id', 'status', 'awaiting_from_class_arm_id', 'awaiting_admission_at', 'admission_date'];

    protected function casts(): array
    {
        return ['admission_date' => 'date', 'awaiting_admission_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'class_arm_id');
    }

    public function awaitingFromClassArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'awaiting_from_class_arm_id');
    }
}
