<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['academic_level_id', 'class_id', 'grading_scheme_id', 'name', 'scope_key', 'show_subject_remarks'];

    protected function casts(): array
    {
        return ['show_subject_remarks' => 'boolean'];
    }

    public function components(): HasMany
    {
        return $this->hasMany(ResultTemplateComponent::class)->orderBy('position');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class, 'grading_scheme_id');
    }
}
