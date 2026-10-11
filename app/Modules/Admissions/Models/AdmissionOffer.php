<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdmissionOffer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['candidate_id', 'offered_by', 'offer_date', 'response_deadline', 'status', 'deposit_required', 'deposit_paid'];

    protected function casts(): array
    {
        return ['offer_date' => 'date', 'response_deadline' => 'date', 'deposit_required' => 'boolean', 'deposit_paid' => 'boolean'];
    }
}
