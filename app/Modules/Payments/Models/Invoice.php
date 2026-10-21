<?php

namespace App\Modules\Payments\Models;

use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use BelongsToTenant;

    public const KIND_TERM_FEE = 'term_fee';

    public const KIND_PART_PAYMENT = 'part_payment';

    public const KIND_ONE_OFF = 'one_off';

    public const KIND_REGISTRATION = 'registration';

    public const KIND_OFFER_DEPOSIT = 'offer_deposit';

    protected $fillable = ['student_id', 'term_fee_student_id', 'candidate_id', 'term_id', 'purpose', 'kind', 'creation_source', 'amount_kobo', 'due_date', 'status', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (! $invoice->kind) {
                $invoice->kind = match ($invoice->purpose) {
                    'Tuition' => self::KIND_TERM_FEE,
                    'Registration' => self::KIND_REGISTRATION,
                    'Offer Deposit' => self::KIND_OFFER_DEPOSIT,
                    default => self::KIND_ONE_OFF,
                };
            }
            $invoice->creation_source ??= 'legacy';
            if ($invoice->kind === self::KIND_TERM_FEE) {
                $invoice->term_fee_student_id ??= $invoice->student_id;
            }
        });
    }

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount_kobo' => 'integer'];
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
