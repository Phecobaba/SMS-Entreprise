<?php

namespace App\Modules\Academics\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    use BelongsToTenant;

    protected $table = 'academic_sessions';

    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
