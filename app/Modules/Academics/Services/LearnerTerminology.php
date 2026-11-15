<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use Illuminate\Support\Collection;

class LearnerTerminology
{
    public static function defaultForExistingSectionName(string $name): string
    {
        return str_contains(mb_strtolower($name), 'nursery') || str_contains(mb_strtolower($name), 'primary')
            ? 'Pupil'
            : 'Student';
    }

    public function word(mixed $context = null, bool $plural = false, bool $capitalized = true): string
    {
        $term = $this->term($context);
        $word = $plural ? $term.'s' : $term;

        return $capitalized ? ucfirst($word) : lcfirst($word);
    }

    public function term(mixed $context = null): string
    {
        if ($context instanceof Student) {
            $context->loadMissing('classArm.schoolClass.level.section');

            return $this->sectionTerm($context->classArm?->schoolClass?->level?->section);
        }

        if ($context instanceof ClassArm) {
            $context->loadMissing('schoolClass.level.section');

            return $this->sectionTerm($context->schoolClass?->level?->section);
        }

        if ($context instanceof SchoolClass) {
            $context->loadMissing('level.section');

            return $this->sectionTerm($context->level?->section);
        }

        if ($context instanceof Section) {
            return $this->sectionTerm($context);
        }

        if ($context instanceof Collection || is_array($context)) {
            $terms = collect($context)->map(fn ($item): string => $this->term($item))->unique()->values();

            return $terms->count() === 1 ? $terms->first() : 'Student';
        }

        return 'Student';
    }

    private function sectionTerm(?Section $section): string
    {
        return in_array($section?->learner_term, ['Pupil', 'Student'], true)
            ? $section->learner_term
            : 'Student';
    }
}
