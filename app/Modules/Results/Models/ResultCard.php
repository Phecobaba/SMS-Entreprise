<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ResultCard extends Model
{
    use BelongsToTenant;

    protected $fillable = ['student_id', 'term_id', 'class_arm_id', 'class_teacher_comment', 'principal_comment', 'proprietor_comment', 'next_term_begins', 'compiled_at'];

    protected function casts(): array
    {
        return ['next_term_begins' => 'date', 'compiled_at' => 'datetime'];
    }
}
