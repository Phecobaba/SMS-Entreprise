<?php

namespace App\Modules\Assignments\Models;

use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['assignment_id', 'student_id', 'status', 'marked_by', 'marked_at'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function casts(): array
    {
        return ['marked_at' => 'datetime'];
    }
}
