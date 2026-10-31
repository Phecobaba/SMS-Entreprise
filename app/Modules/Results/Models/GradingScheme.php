<?php

namespace App\Modules\Results\Models;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section_id', 'name'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_score');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ResultTemplate::class, 'grading_scheme_id');
    }
}
