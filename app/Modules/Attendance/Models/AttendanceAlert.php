<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AttendanceAlert extends Model
{
    use BelongsToTenant;

    protected $fillable = ['class_arm_id', 'date', 'resolved'];

    protected function casts(): array
    {
        return ['date' => 'date', 'resolved' => 'boolean'];
    }
}
