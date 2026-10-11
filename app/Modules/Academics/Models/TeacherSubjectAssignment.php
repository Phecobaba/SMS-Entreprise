<?php

namespace App\Modules\Academics\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherSubjectAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'class_id', 'subject_id'];
}
