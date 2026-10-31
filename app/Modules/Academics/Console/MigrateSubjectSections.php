<?php

namespace App\Modules\Academics\Console;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Services\SubjectReferences as AcademicSubjectReferences;
use App\Modules\Assignments\Services\SubjectReferences as AssignmentSubjectReferences;
use App\Modules\CBT\Services\SubjectReferences as CbtSubjectReferences;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use App\Modules\ENotes\Services\SubjectReferences as ENoteSubjectReferences;
use App\Modules\Results\Services\SubjectReferences as ResultSubjectReferences;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateSubjectSections extends Command
{
    protected $signature = 'oakfield:migrate-subject-sections
        {--dry-run : Print the planned changes without writing}
        {--actor= : User ID recorded for the real migration audit}';

    protected $description = 'Assign or split existing subjects so every subject belongs to one section';

    public function handle(
        TenantContext $tenant,
        AcademicSubjectReferences $academics,
        ResultSubjectReferences $results,
        CbtSubjectReferences $cbt,
        AssignmentSubjectReferences $assignments,
        ENoteSubjectReferences $enotes,
        AuditRecorder $audit,
    ): int {
        if (! Schema::hasColumn('subjects', 'section_id')) {
            $this->error('Run Migration A (2026_10_23_000500_add_section_to_subjects) first.');

            return self::FAILURE;
        }

        $school = School::find((int) config('tenancy.default_school_id'));
        if (! $school) {
            $this->error('The configured school does not exist.');

            return self::FAILURE;
        }
        $tenant->set($school->id);

        $actor = null;
        if (! $this->option('dry-run')) {
            $actor = User::find((int) $this->option('actor'));
            if (! $actor) {
                $this->error('A valid user from this school is required for the audit log. Re-run with --actor=<user-id>.');

                return self::FAILURE;
            }
        }

        $buildPlan = fn () => $this->plan($academics, $results, $cbt, $assignments, $enotes);
        $plan = $buildPlan();
        if ($this->option('dry-run')) {
            $this->renderPlan($plan, true);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $academics, $results, $cbt, $assignments, $enotes, $audit, $actor): void {
            foreach ($plan['subjects'] as $item) {
                /** @var Subject $subject */
                $subject = Subject::findOrFail($item['subject_id']);
                $subject->update(['section_id' => $item['kept_section_id']]);
                $subjectIdBySection = [$item['kept_section_id'] => $subject->id];
                foreach ($item['target_section_ids'] as $sectionId) {
                    if ($sectionId === $item['kept_section_id']) {
                        continue;
                    }
                    $copy = Subject::firstOrCreate(
                        ['section_id' => $sectionId, 'name' => $subject->name],
                    );
                    $subjectIdBySection[$sectionId] = $copy->id;
                }

                $results->repoint($subject, $subjectIdBySection);
                $cbt->repoint($subject, $subjectIdBySection);
                $assignments->repoint($subject, $subjectIdBySection);
                $enotes->repoint($subject, $subjectIdBySection);
                $academics->repoint($subject, $subjectIdBySection);
            }

            $audit->record($actor, 'subject_sections_migrated', null, [
                'command' => 'oakfield:migrate-subject-sections',
                'counts' => $plan['counts'],
            ]);
        });

        $this->renderPlan($plan, false);

        return self::SUCCESS;
    }

    private function plan(
        AcademicSubjectReferences $academics,
        ResultSubjectReferences $results,
        CbtSubjectReferences $cbt,
        AssignmentSubjectReferences $assignments,
        ENoteSubjectReferences $enotes,
    ): array {
        $sectionIds = Section::orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $counts = array_fill_keys([
            'subjects_assigned', 'subjects_split', 'subject_copies', 'class_subjects',
            'teacher_subject_assignments', 'results', 'exams', 'assignments', 'enotes',
        ], 0);
        $subjects = [];

        foreach (Subject::orderBy('id')->get() as $subject) {
            $usedSectionIds = collect([
                ...$academics->sectionIds($subject),
                ...$results->sectionIds($subject),
                ...$cbt->sectionIds($subject),
                ...$assignments->sectionIds($subject),
                ...$enotes->sectionIds($subject),
            ])->unique()->sort()->values()->all();
            $targetSectionIds = $usedSectionIds ?: $sectionIds;
            if ($targetSectionIds === []) {
                continue;
            }
            $keptSectionId = in_array((int) $subject->section_id, $targetSectionIds, true)
                ? (int) $subject->section_id
                : $targetSectionIds[0];
            $counts['subjects_assigned'] += (int) ((int) $subject->section_id !== $keptSectionId);
            $counts['subjects_split'] += (int) (count($usedSectionIds) > 1);

            foreach ($targetSectionIds as $sectionId) {
                if ($sectionId !== $keptSectionId
                    && ! Subject::where('section_id', $sectionId)->where('name', $subject->name)->exists()) {
                    $counts['subject_copies']++;
                }
            }

            if (count($usedSectionIds) > 1) {
                $academicCounts = $academics->repointCounts($subject, $keptSectionId);
                $counts['class_subjects'] += $academicCounts['class_subjects'];
                $counts['teacher_subject_assignments'] += $academicCounts['teacher_subject_assignments'];
                $counts['results'] += $results->repointCount($subject, $keptSectionId);
                $counts['exams'] += $cbt->repointCount($subject, $keptSectionId);
                $counts['assignments'] += $assignments->repointCount($subject, $keptSectionId);
                $counts['enotes'] += $enotes->repointCount($subject, $keptSectionId);
            }

            if ((int) $subject->section_id !== $keptSectionId || count($targetSectionIds) > 1) {
                $subjects[] = [
                    'subject_id' => $subject->id,
                    'kept_section_id' => $keptSectionId,
                    'target_section_ids' => $targetSectionIds,
                ];
            }
        }

        return compact('subjects', 'counts');
    }

    private function renderPlan(array $plan, bool $dryRun): void
    {
        $this->info($dryRun ? 'DRY RUN — no changes written.' : 'Subject section migration applied.');
        $this->table(['Change', 'Count'], collect($plan['counts'])->map(
            fn (int $count, string $label) => [str_replace('_', ' ', $label), $count],
        )->values()->all());
    }
}
