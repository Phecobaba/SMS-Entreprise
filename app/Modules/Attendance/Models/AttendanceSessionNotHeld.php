<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSessionNotHeld extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'attendance_sessions_not_held';

    protected $fillable = ['section_id', 'date', 'session', 'marked_by', 'created_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'created_at' => 'datetime'];
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
