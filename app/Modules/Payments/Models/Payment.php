<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['invoice_id', 'amount_kobo', 'method', 'gateway_reference', 'confirmed_by', 'paid_at', 'receipt_number'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'paid_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
