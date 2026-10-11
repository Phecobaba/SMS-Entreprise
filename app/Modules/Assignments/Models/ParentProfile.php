<?php

namespace App\Modules\Assignments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ParentProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'parents';

    protected $fillable = ['user_id'];
}
