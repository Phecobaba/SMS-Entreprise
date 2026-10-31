<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\Subject;
use App\Modules\Assignments\Services\SubjectReferences as AssignmentSubjectReferences;
use App\Modules\CBT\Services\SubjectReferences as CbtSubjectReferences;
use App\Modules\ENotes\Services\SubjectReferences as ENoteSubjectReferences;
use App\Modules\Results\Services\SubjectReferences as ResultSubjectReferences;
use Illuminate\Validation\ValidationException;

class SubjectManagement
{
    public function __construct(
        private readonly SubjectReferences $academics,
        private readonly ResultSubjectReferences $results,
        private readonly CbtSubjectReferences $cbt,
        private readonly AssignmentSubjectReferences $assignments,
        private readonly ENoteSubjectReferences $enotes,
    ) {}

    public function usage(Subject $subject): array
    {
        return array_merge(
            $this->academics->usage($subject),
            $this->results->usage($subject),
            $this->cbt->usage($subject),
            $this->assignments->usage($subject),
            $this->enotes->usage($subject),
        );
    }

    public function assertUnused(Subject $subject, string $action): void
    {
        $usage = array_filter($this->usage($subject), fn (int $count) => $count > 0);
        if ($usage === []) {
            return;
        }

        $details = collect($usage)->map(
            fn (int $count, string $label) => $count.' '.str_replace('_', ' ', $label),
        )->implode(', ');
        throw ValidationException::withMessages([
            'subject' => $subject->name.' cannot be '.$action.' because it has '.$details.'. Remove those links first; no history was changed.',
        ]);
    }
}
