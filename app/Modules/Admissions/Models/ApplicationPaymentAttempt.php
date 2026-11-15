<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Payments\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicationPaymentAttempt extends Model
{
    use BelongsToTenant;

    protected $fillable = ['application_id', 'active_application_id', 'reference', 'amount_kobo', 'currency', 'authorization_url', 'status', 'last_activity_at', 'late_success'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'last_activity_at' => 'datetime', 'late_success' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
