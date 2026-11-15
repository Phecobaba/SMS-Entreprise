<?php

namespace App\Modules\StaffHR\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffDisciplinaryLog extends Model
{
    use BelongsToTenant;

    protected $table = 'staff_disciplinary_log';

    protected $fillable = ['staff_id', 'type', 'date', 'description', 'issued_by', 'resolution'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
