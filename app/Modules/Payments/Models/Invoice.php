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

    protected $fillable = ['student_id', 'candidate_id', 'term_id', 'purpose', 'amount_kobo', 'due_date', 'status', 'created_by'];

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

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
