<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['invoice_id', 'fee_item_id', 'description', 'amount_kobo', 'is_required'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'is_required' => 'boolean'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }
}
