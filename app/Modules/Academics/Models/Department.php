<?php

namespace App\Modules\Academics\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name'];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
