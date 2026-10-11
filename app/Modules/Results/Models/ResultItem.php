<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['result_id', 'assessment_component_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }
}
