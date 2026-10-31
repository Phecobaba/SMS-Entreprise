<?php

namespace App\Modules\Results\Models;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section_id', 'academic_level_id', 'class_id', 'grading_scheme_id', 'name', 'scope_key', 'active_scope_key', 'show_subject_remarks', 'retired_at', 'retired_by'];

    protected function casts(): array
    {
        return ['show_subject_remarks' => 'boolean', 'retired_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (ResultTemplate $template): void {
            if ($template->retired_at === null && $template->active_scope_key === null) {
                $template->active_scope_key = $template->scope_key;
            }
        });
    }

    public function components(): HasMany
    {
        return $this->hasMany(ResultTemplateComponent::class)->orderBy('position');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class, 'grading_scheme_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class, 'result_template_id');
    }

    public function retiredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    public function scopeLabel(): string
    {
        if ($this->class_id !== null) {
            return trim(($this->level?->name ?? '').' '.($this->schoolClass?->name ?? ''));
        }

        return $this->academic_level_id !== null
            ? ($this->level?->name ?? 'Level')
            : 'All levels in '.($this->section?->name ?? 'Section');
    }
}
