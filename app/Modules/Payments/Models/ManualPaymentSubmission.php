<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPaymentSubmission extends Model
{
    use BelongsToTenant;

    public const STATUS_AWAITING = 'Awaiting approval';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'invoice_id', 'bank', 'teller_number', 'active_teller_key', 'paid_on',
        'amount_kobo', 'photo_path', 'recorded_by', 'status', 'decided_by',
        'decided_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount_kobo' => 'integer', 'decided_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
