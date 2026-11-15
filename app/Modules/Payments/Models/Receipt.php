<?php

namespace App\Modules\Payments\Models;

use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'payment_id', 'application_id', 'application_payment_attempt_id', 'number',
        'school_name', 'school_address', 'school_phone', 'school_logo_data_uri',
        'payer_name', 'child_name', 'class_or_level', 'items', 'amount_kobo',
        'currency', 'method', 'gateway_reference', 'balance_after_kobo',
        'received_by_name', 'issued_at', 'emailed_at', 'mail_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array', 'amount_kobo' => 'integer', 'balance_after_kobo' => 'integer',
            'issued_at' => 'datetime', 'emailed_at' => 'datetime', 'mail_failed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationAttempt(): BelongsTo
    {
        return $this->belongsTo(ApplicationPaymentAttempt::class, 'application_payment_attempt_id');
    }
}
