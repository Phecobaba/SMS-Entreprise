<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRole extends Pivot
{
    use BelongsToTenant;

    protected $table = 'user_roles';

    public $timestamps = false;
}
