<?php

namespace App\Modules\Assignments\Models;

use App\Modules\Academics\Models\Subject;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['subject_id', 'class_arm_id', 'title', 'description', 'due_date', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
