<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name'];

    public function scheduleLines(): HasMany
    {
        return $this->hasMany(FeeScheduleLine::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
