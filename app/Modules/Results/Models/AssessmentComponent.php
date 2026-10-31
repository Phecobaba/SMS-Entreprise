<?php

namespace App\Modules\Results\Models;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section_id', 'name', 'max_score'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    protected function casts(): array
    {
        return ['max_score' => 'decimal:2'];
    }

    public function resultItems(): HasMany
    {
        return $this->hasMany(ResultItem::class, 'assessment_component_id');
    }

    public function templateEntries(): HasMany
    {
        return $this->hasMany(ResultTemplateComponent::class, 'assessment_component_id');
    }
}
