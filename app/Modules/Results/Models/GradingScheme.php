<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name'];

    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_score');
    }
}
