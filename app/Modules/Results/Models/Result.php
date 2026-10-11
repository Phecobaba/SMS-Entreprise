<?php

namespace App\Modules\Results\Models;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Result extends Model
{
    use BelongsToTenant;

    protected $fillable = ['student_id', 'class_arm_id', 'subject_id', 'term_id', 'result_template_id', 'status', 'submitted_by', 'approved_by', 'rejection_reason', 'total_score', 'percentage', 'grade', 'remark', 'locked_at'];

    protected function casts(): array
    {
        return ['total_score' => 'decimal:2', 'percentage' => 'decimal:2', 'locked_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ResultItem::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ResultTemplate::class, 'result_template_id');
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'class_arm_id');
    }

    public function audit(): HasMany
    {
        return $this->hasMany(ResultAuditLog::class)->orderBy('created_at')->orderBy('id');
    }
}
