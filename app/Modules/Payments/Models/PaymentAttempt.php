<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['invoice_id', 'reference', 'amount_kobo', 'initiated_by', 'authorization_url', 'status', 'last_checked_at'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'last_checked_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
