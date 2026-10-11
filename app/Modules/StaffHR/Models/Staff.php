<?php

namespace App\Modules\StaffHR\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use BelongsToTenant;

    protected $table = 'staff';

    protected $fillable = ['user_id', 'role_title', 'employment_date', 'contract_type', 'status'];

    protected function casts(): array
    {
        return ['employment_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryHistory(): HasMany
    {
        return $this->hasMany(StaffSalaryHistory::class)->orderBy('effective_from');
    }
}
