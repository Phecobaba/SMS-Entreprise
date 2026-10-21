<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AttendanceAlertSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section_id', 'morning_due_at', 'afternoon_due_at', 'updated_by'];
}
