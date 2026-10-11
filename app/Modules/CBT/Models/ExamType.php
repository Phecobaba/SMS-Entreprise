<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name'];
}
