<?php

namespace App\Modules\Academics\Models;

use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SchoolClass extends Model
{
    use BelongsToTenant;

    protected $table = 'classes';

    protected $fillable = ['academic_level_id', 'department_id', 'name', 'position'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function arms(): HasMany
    {
        return $this->hasMany(ClassArm::class, 'class_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class, ClassArm::class, 'class_id', 'class_arm_id');
    }

    public function activeStudents(): HasManyThrough
    {
        return $this->students()->active();
    }
}
