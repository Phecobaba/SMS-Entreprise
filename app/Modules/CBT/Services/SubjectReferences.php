<?php

namespace App\Modules\CBT\Services;

use App\Modules\Academics\Models\Subject;
use App\Modules\CBT\Models\Exam;

class SubjectReferences
{
    public function usage(Subject $subject): array
    {
        return ['exams' => Exam::where('subject_id', $subject->id)->count()];
    }

    public function sectionIds(Subject $subject): array
    {
        return Exam::with('schoolClass.level')->where('subject_id', $subject->id)->get()
            ->pluck('schoolClass.level.section_id')->filter()->map(fn ($id) => (int) $id)
            ->unique()->sort()->values()->all();
    }

    public function repoint(Subject $subject, array $subjectIdBySection): int
    {
        $changed = 0;
        foreach (Exam::with('schoolClass.level')->where('subject_id', $subject->id)->get() as $exam) {
            $sectionId = (int) $exam->schoolClass?->level?->section_id;
            $targetId = (int) ($subjectIdBySection[$sectionId] ?? $subject->id);
            if ($targetId !== $subject->id) {
                $exam->update(['subject_id' => $targetId]);
                $changed++;
            }
        }

        return $changed;
    }

    public function repointCount(Subject $subject, int $keptSectionId): int
    {
        return Exam::with('schoolClass.level')->where('subject_id', $subject->id)->get()
            ->filter(fn (Exam $exam) => ($sectionId = $exam->schoolClass?->level?->section_id)
                && (int) $sectionId !== $keptSectionId)->count();
    }
}
