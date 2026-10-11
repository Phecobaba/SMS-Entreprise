<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'class_arm_id', 'candidate_id', 'admission_date'];

    protected function casts(): array
    {
        return ['admission_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class, 'class_arm_id');
    }
}
