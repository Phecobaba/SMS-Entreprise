<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use Illuminate\Validation\ValidationException;

class SubjectReferences
{
    public function usage(Subject $subject): array
    {
        return [
            'classes' => ClassSubject::where('subject_id', $subject->id)->count(),
            'teachers' => TeacherSubjectAssignment::where('subject_id', $subject->id)->count(),
        ];
    }

    public function sectionIds(Subject $subject): array
    {
        return ClassSubject::with('schoolClass.level')->where('subject_id', $subject->id)->get()
            ->pluck('schoolClass.level.section_id')->filter()->map(fn ($id) => (int) $id)
            ->unique()->sort()->values()->all();
    }

    public function repoint(Subject $subject, array $subjectIdBySection): array
    {
        $counts = ['class_subjects' => 0, 'teacher_subject_assignments' => 0];
        $rows = ClassSubject::with('schoolClass.level')->where('subject_id', $subject->id)->get();

        foreach ($rows as $row) {
            $sectionId = (int) $row->schoolClass?->level?->section_id;
            $targetId = (int) ($subjectIdBySection[$sectionId] ?? $subject->id);
            if ($targetId === $subject->id) {
                continue;
            }

            ClassSubject::firstOrCreate(['class_id' => $row->class_id, 'subject_id' => $targetId]);
            $counts['teacher_subject_assignments'] += TeacherSubjectAssignment::where('class_id', $row->class_id)
                ->where('subject_id', $subject->id)->update(['subject_id' => $targetId]);
            $row->delete();
            $counts['class_subjects']++;
        }

        return $counts;
    }

    public function repointCounts(Subject $subject, int $keptSectionId): array
    {
        $classIds = ClassSubject::with('schoolClass.level')->where('subject_id', $subject->id)->get()
            ->filter(fn (ClassSubject $row) => (int) $row->schoolClass?->level?->section_id !== $keptSectionId)
            ->pluck('class_id');

        return [
            'class_subjects' => $classIds->count(),
            'teacher_subject_assignments' => TeacherSubjectAssignment::where('subject_id', $subject->id)
                ->whereIn('class_id', $classIds)->count(),
        ];
    }

    public function assertClassSubjectSection(int $classId, int $subjectId): void
    {
        $sectionId = SchoolClass::with('level')->find($classId)?->level?->section_id;
        $matches = $sectionId && Subject::whereKey($subjectId)->where('section_id', $sectionId)->exists();

        if (! $matches) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject from the same section as the class.']);
        }
    }
}
