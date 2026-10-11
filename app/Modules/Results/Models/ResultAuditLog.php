<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ResultAuditLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'result_audit_log';

    protected $fillable = ['result_id', 'action', 'user_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
