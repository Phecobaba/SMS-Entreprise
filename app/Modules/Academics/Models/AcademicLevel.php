<?php

namespace App\Modules\Academics\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section_id', 'name', 'registration_prefix', 'registration_code', 'assessed_in_person'];

    protected function casts(): array
    {
        return ['assessed_in_person' => 'boolean'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'academic_level_id');
    }
}
