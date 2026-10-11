<?php

namespace App\Modules\Assignments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ParentStudent extends Model
{
    use BelongsToTenant;

    protected $table = 'parent_student';

    protected $fillable = ['parent_id', 'student_id'];
}
