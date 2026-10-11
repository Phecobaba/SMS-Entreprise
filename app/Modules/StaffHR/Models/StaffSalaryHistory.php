<?php

namespace App\Modules\StaffHR\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StaffSalaryHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'staff_salary_history';

    protected $fillable = ['staff_id', 'amount_kobo', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['amount_kobo' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date'];
    }
}
