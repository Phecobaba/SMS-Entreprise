<?php

namespace App\Modules\Academics\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    use BelongsToTenant;

    protected $table = 'academic_sessions';

    protected $fillable = ['name', 'start_date', 'end_date'];

    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class, 'session_id');
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }
}
