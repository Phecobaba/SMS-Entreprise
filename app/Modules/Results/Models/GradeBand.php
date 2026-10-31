<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeBand extends Model
{
    use BelongsToTenant;

    protected $fillable = ['grading_scheme_id', 'min_score', 'max_score', 'grade', 'remark'];

    protected function casts(): array
    {
        return ['min_score' => 'integer', 'max_score' => 'integer'];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class, 'grading_scheme_id');
    }
}
