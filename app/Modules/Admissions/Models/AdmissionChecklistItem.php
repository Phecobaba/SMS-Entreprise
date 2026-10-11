<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AdmissionChecklistItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['label'];
}
