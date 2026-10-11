<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultTemplateComponent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['result_template_id', 'assessment_component_id', 'position'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }
}
