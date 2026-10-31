<?php

namespace App\Modules\ENotes\Services;

use App\Modules\Academics\Models\Subject;
use App\Modules\ENotes\Models\ENote;

class SubjectReferences
{
    public function usage(Subject $subject): array
    {
        return ['e-notes' => ENote::where('subject_id', $subject->id)->count()];
    }

    public function sectionIds(Subject $subject): array
    {
        return ENote::with('schoolClass.level')->where('subject_id', $subject->id)->get()
            ->pluck('schoolClass.level.section_id')->filter()->map(fn ($id) => (int) $id)
            ->unique()->sort()->values()->all();
    }

    public function repoint(Subject $subject, array $subjectIdBySection): int
    {
        $changed = 0;
        foreach (ENote::with('schoolClass.level')->where('subject_id', $subject->id)->get() as $note) {
            $sectionId = (int) $note->schoolClass?->level?->section_id;
            $targetId = (int) ($subjectIdBySection[$sectionId] ?? $subject->id);
            if ($targetId !== $subject->id) {
                $note->update(['subject_id' => $targetId]);
                $changed++;
            }
        }

        return $changed;
    }

    public function repointCount(Subject $subject, int $keptSectionId): int
    {
        return ENote::with('schoolClass.level')->where('subject_id', $subject->id)->get()
            ->filter(fn (ENote $note) => ($sectionId = $note->schoolClass?->level?->section_id)
                && (int) $sectionId !== $keptSectionId)->count();
    }
}
