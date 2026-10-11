<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeBand extends Model
{
    use BelongsToTenant;

    protected $fillable = ['grading_scheme_id', 'min_score', 'max_score', 'grade', 'remark'];

    protected function casts(): array
    {
        return ['min_score' => 'decimal:2', 'max_score' => 'decimal:2'];
    }
}
