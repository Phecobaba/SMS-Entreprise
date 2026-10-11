<?php

namespace App\Modules\StaffHR\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StaffExitRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = ['staff_id', 'exit_type', 'exit_date', 'notice_period', 'notes'];

    protected function casts(): array
    {
        return ['exit_date' => 'date'];
    }
}
