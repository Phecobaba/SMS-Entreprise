<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RegistrationNumberSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['sequence_key', 'next_number'];
}
