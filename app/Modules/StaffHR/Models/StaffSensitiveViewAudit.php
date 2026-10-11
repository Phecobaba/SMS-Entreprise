<?php

namespace App\Modules\StaffHR\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StaffSensitiveViewAudit extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'staff_sensitive_view_audit';

    protected $fillable = ['staff_id', 'viewed_by', 'data_type', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
