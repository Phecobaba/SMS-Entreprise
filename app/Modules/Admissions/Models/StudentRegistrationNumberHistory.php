<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRegistrationNumberHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'student_registration_number_history';

    public $timestamps = false;

    protected $fillable = [
        'student_id', 'old_number', 'new_number', 'sequence_value', 'issued_year',
        'section_code', 'level_code', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'sequence_value' => 'integer', 'issued_year' => 'integer'];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
