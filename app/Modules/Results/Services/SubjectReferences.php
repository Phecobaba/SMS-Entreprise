<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\Subject;
use App\Modules\Results\Models\Result;

class SubjectReferences
{
    public function usage(Subject $subject): array
    {
        return ['results' => Result::where('subject_id', $subject->id)->count()];
    }

    public function sectionIds(Subject $subject): array
    {
        return Result::with('classArm.schoolClass.level')->where('subject_id', $subject->id)->get()
            ->pluck('classArm.schoolClass.level.section_id')->filter()->map(fn ($id) => (int) $id)
            ->unique()->sort()->values()->all();
    }

    public function repoint(Subject $subject, array $subjectIdBySection): int
    {
        $changed = 0;
        foreach (Result::with('classArm.schoolClass.level')->where('subject_id', $subject->id)->get() as $result) {
            $sectionId = (int) $result->classArm?->schoolClass?->level?->section_id;
            $targetId = (int) ($subjectIdBySection[$sectionId] ?? $subject->id);
            if ($targetId !== $subject->id) {
                $result->update(['subject_id' => $targetId]);
                $changed++;
            }
        }

        return $changed;
    }

    public function repointCount(Subject $subject, int $keptSectionId): int
    {
        return Result::with('classArm.schoolClass.level')->where('subject_id', $subject->id)->get()
            ->filter(fn (Result $result) => ($sectionId = $result->classArm?->schoolClass?->level?->section_id)
                && (int) $sectionId !== $keptSectionId)->count();
    }
}
