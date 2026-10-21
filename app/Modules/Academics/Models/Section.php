<?php

namespace App\Modules\Academics\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'head_title', 'classes_arranged_by', 'classes_arranged_at'];

    protected function casts(): array
    {
        return ['classes_arranged_at' => 'datetime'];
    }

    public function classesArrangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classes_arranged_by');
    }

    public function heads(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'section_user')
            ->withPivot('school_id')
            ->wherePivot('school_id', app(TenantContext::class)->id())
            ->withTimestamps();
    }

    public function levels(): HasMany
    {
        return $this->hasMany(AcademicLevel::class);
    }
}
