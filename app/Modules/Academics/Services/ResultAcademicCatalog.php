<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

class ResultAcademicCatalog
{
    public function assertClassSubject(int $classId, int $subjectId): void
    {
        if (! ClassSubject::where('class_id', $classId)->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'This subject is not offered by the class.']);
        }
    }

    public function assertExamSubject(int $classId, int $subjectId): void
    {
        if (! ClassSubject::where('class_id', $classId)->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Assign this subject to the class before creating an exam.']);
        }
    }

    public function assertTerm(int $termId): void
    {
        if (! AcademicTerm::whereKey($termId)->exists()) {
            throw ValidationException::withMessages(['term_id' => 'Choose a term in this school.']);
        }
    }

    public function assertTemplateScope(int $levelId, ?int $classId): void
    {
        if (! AcademicLevel::whereKey($levelId)->exists()) {
            throw ValidationException::withMessages(['academic_level_id' => 'Choose an academic level in this school.']);
        }
        if ($classId !== null && ! SchoolClass::whereKey($classId)->where('academic_level_id', $levelId)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'The class must belong to the selected academic level.']);
        }
    }

    public function resultContext(int $armId, int $subjectId, int $termId): array
    {
        $placement = $this->placement($armId);
        if (! Subject::whereKey($subjectId)->exists()
            || ! ClassSubject::where('class_id', $placement['class_id'])->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'This subject is not assigned to the student’s class.']);
        }
        if (! AcademicTerm::whereKey($termId)->exists()) {
            throw ValidationException::withMessages(['term_id' => 'Choose a term in this school.']);
        }

        return $placement;
    }

    public function placement(int $armId): array
    {
        $arm = ClassArm::with('schoolClass')->find($armId);
        if (! $arm || ! $arm->schoolClass) {
            throw ValidationException::withMessages(['student_id' => 'The student is not assigned to a valid class arm.']);
        }

        return ['class_id' => $arm->class_id, 'academic_level_id' => $arm->schoolClass->academic_level_id];
    }

    public function labels(array $subjectIds, array $termIds): array
    {
        return [
            'subjects' => Subject::whereIn('id', $subjectIds)->pluck('name', 'id'),
            'terms' => AcademicTerm::whereIn('id', $termIds)->pluck('name', 'id'),
        ];
    }

    public function options(?User $viewer = null): array
    {
        $levels = AcademicLevel::query();
        $classes = SchoolClass::query();
        if ($viewer) {
            app(SectionAccess::class)->scopeLevels($levels, $viewer);
            app(SectionAccess::class)->scopeClasses($classes, $viewer);
        }

        return [
            'levels' => $levels->orderBy('name')->get(),
            'classes' => $classes->with('level')->orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'terms' => AcademicTerm::with('session')->orderByDesc('start_date')->get(),
        ];
    }
}
