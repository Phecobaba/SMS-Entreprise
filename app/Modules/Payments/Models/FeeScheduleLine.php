<?php

namespace App\Modules\Payments\Models;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeScheduleLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['term_id', 'scope_type', 'academic_level_id', 'class_id', 'department_id', 'fee_item_id', 'amount_kobo', 'is_required'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'is_required' => 'boolean'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
