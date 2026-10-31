<?php

namespace App\Modules\Assignments\Services;

use App\Modules\Academics\Models\Subject;
use App\Modules\Assignments\Models\Assignment;

class SubjectReferences
{
    public function usage(Subject $subject): array
    {
        return ['assignments' => Assignment::where('subject_id', $subject->id)->count()];
    }

    public function sectionIds(Subject $subject): array
    {
        return Assignment::with('classArm.schoolClass.level')->where('subject_id', $subject->id)->get()
            ->pluck('classArm.schoolClass.level.section_id')->filter()->map(fn ($id) => (int) $id)
            ->unique()->sort()->values()->all();
    }

    public function repoint(Subject $subject, array $subjectIdBySection): int
    {
        $changed = 0;
        foreach (Assignment::with('classArm.schoolClass.level')->where('subject_id', $subject->id)->get() as $assignment) {
            $sectionId = (int) $assignment->classArm?->schoolClass?->level?->section_id;
            $targetId = (int) ($subjectIdBySection[$sectionId] ?? $subject->id);
            if ($targetId !== $subject->id) {
                $assignment->update(['subject_id' => $targetId]);
                $changed++;
            }
        }

        return $changed;
    }

    public function repointCount(Subject $subject, int $keptSectionId): int
    {
        return Assignment::with('classArm.schoolClass.level')->where('subject_id', $subject->id)->get()
            ->filter(fn (Assignment $assignment) => ($sectionId = $assignment->classArm?->schoolClass?->level?->section_id)
                && (int) $sectionId !== $keptSectionId)->count();
    }
}
