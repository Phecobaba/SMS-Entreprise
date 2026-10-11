<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AssessmentComponent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'max_score'];

    protected function casts(): array
    {
        return ['max_score' => 'decimal:2'];
    }
}
