<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = ['student_id', 'class_arm_id', 'date', 'status', 'marked_by', 'locked'];

    protected function casts(): array
    {
        return ['date' => 'date', 'locked' => 'boolean'];
    }
}
