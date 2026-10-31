<?php

namespace App\Modules\Results\Console;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateResultSections extends Command
{
    protected $signature = 'oakfield:migrate-result-sections
        {--dry-run : Print the planned changes without writing}
        {--actor= : User ID recorded for the real migration audit}';

    protected $description = 'Assign or split Result grade scales and components so each belongs to one Section';

    public function handle(TenantContext $tenant, AuditRecorder $audit): int
    {
        if (! Schema::hasColumn('grading_schemes', 'section_id') || ! Schema::hasColumn('assessment_components', 'section_id')) {
            $this->error('Run Migration A (2026_10_26_000100_add_section_to_result_configuration) first.');

            return self::FAILURE;
        }

        $school = School::find((int) config('tenancy.default_school_id'));
        if (! $school) {
            $this->error('The configured school does not exist.');

            return self::FAILURE;
        }
        $tenant->set($school->id);

        if (AuditLog::where('action', 'result_configuration_sections_migrated')->exists()) {
            $this->error('The Result configuration Section migration has already been applied.');

            return self::FAILURE;
        }

        $actor = null;
        if (! $this->option('dry-run')) {
            $actor = User::find((int) $this->option('actor'));
            if (! $actor) {
                $this->error('A valid user from this school is required. Re-run with --actor=<user-id>.');

                return self::FAILURE;
            }
        }

        $plan = $this->plan($school->id);
        if ($this->option('dry-run')) {
            $this->renderPlan($plan, true);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $school, $actor, $audit): void {
            $this->applyScales($plan['scales'], $school->id);
            $this->applyComponents($plan['components'], $school->id);
            $audit->record($actor, 'result_configuration_sections_migrated', null, [
                'command' => 'oakfield:migrate-result-sections',
                'counts' => $plan['counts'],
            ]);
        });

        $this->renderPlan($plan, false);

        return self::SUCCESS;
    }

    private function plan(int $schoolId): array
    {
        $sections = Section::orderBy('id')->get(['id', 'name']);
        $sectionIds = $sections->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $sectionNames = $sections->pluck('name', 'id');
        if ($sectionIds === []) {
            throw new \RuntimeException('Create at least one Section before migrating Result configuration.');
        }

        $counts = array_fill_keys([
            'scales_assigned', 'scales_split', 'scale_copies', 'bands_copied', 'templates_repointed',
            'components_assigned', 'components_split', 'component_copies', 'template_components_repointed',
            'result_items_repointed', 'exams_repointed',
        ], 0);
        $scales = [];
        foreach (DB::table('grading_schemes')->where('school_id', $schoolId)->orderBy('id')->get() as $scale) {
            $used = DB::table('result_templates as rt')
                ->join('academic_levels as l', 'l.id', '=', 'rt.academic_level_id')
                ->where('rt.school_id', $schoolId)->where('rt.grading_scheme_id', $scale->id)
                ->whereNotNull('l.section_id')->distinct()->orderBy('l.section_id')->pluck('l.section_id')
                ->map(fn ($id): int => (int) $id)->all();
            $targets = $used ?: $sectionIds;
            $kept = in_array((int) $scale->section_id, $targets, true) ? (int) $scale->section_id : $targets[0];
            $bands = DB::table('grade_bands')->where('school_id', $schoolId)->where('grading_scheme_id', $scale->id)->count();
            $copies = max(0, count($targets) - 1);
            $counts['scales_assigned'] += (int) ((int) $scale->section_id !== $kept);
            $counts['scales_split'] += (int) (count($used) > 1);
            $counts['scale_copies'] += $copies;
            $counts['bands_copied'] += $copies * $bands;
            $counts['templates_repointed'] += DB::table('result_templates as rt')
                ->join('academic_levels as l', 'l.id', '=', 'rt.academic_level_id')
                ->where('rt.school_id', $schoolId)->where('rt.grading_scheme_id', $scale->id)
                ->where('l.section_id', '!=', $kept)->count();
            $scales[] = [
                'id' => (int) $scale->id, 'name' => $scale->name, 'kept_section_id' => $kept,
                'target_section_ids' => $targets, 'used_section_ids' => $used,
                'target_names' => collect($targets)->map(fn (int $id): string => $sectionNames[$id])->all(),
            ];
        }

        $components = [];
        foreach (DB::table('assessment_components')->where('school_id', $schoolId)->orderBy('id')->get() as $component) {
            $used = DB::table('result_template_components as rtc')
                ->join('result_templates as rt', 'rt.id', '=', 'rtc.result_template_id')
                ->join('academic_levels as l', 'l.id', '=', 'rt.academic_level_id')
                ->where('rtc.school_id', $schoolId)->where('rtc.assessment_component_id', $component->id)
                ->whereNotNull('l.section_id')->distinct()->orderBy('l.section_id')->pluck('l.section_id')
                ->map(fn ($id): int => (int) $id)->all();
            $targets = $used ?: $sectionIds;
            $kept = in_array((int) $component->section_id, $targets, true) ? (int) $component->section_id : $targets[0];
            $copies = max(0, count($targets) - 1);
            $counts['components_assigned'] += (int) ((int) $component->section_id !== $kept);
            $counts['components_split'] += (int) (count($used) > 1);
            $counts['component_copies'] += $copies;
            foreach ($targets as $sectionId) {
                if ($sectionId === $kept) {
                    continue;
                }
                $templateIds = $this->templateIdsForSection($schoolId, $sectionId);
                $counts['template_components_repointed'] += DB::table('result_template_components')
                    ->where('school_id', $schoolId)->where('assessment_component_id', $component->id)
                    ->whereIn('result_template_id', $templateIds)->count();
                $resultIds = DB::table('results')->where('school_id', $schoolId)->whereIn('result_template_id', $templateIds)->pluck('id');
                $counts['result_items_repointed'] += DB::table('result_items')
                    ->where('school_id', $schoolId)->where('assessment_component_id', $component->id)
                    ->whereIn('result_id', $resultIds)->count();
                $classIds = $this->classIdsForSection($schoolId, $sectionId);
                $counts['exams_repointed'] += DB::table('exams')->where('school_id', $schoolId)
                    ->where('assessment_component_id', $component->id)->whereIn('class_id', $classIds)->count();
            }
            $components[] = [
                'id' => (int) $component->id, 'name' => $component->name, 'kept_section_id' => $kept,
                'target_section_ids' => $targets, 'used_section_ids' => $used,
                'target_names' => collect($targets)->map(fn (int $id): string => $sectionNames[$id])->all(),
            ];
        }

        return compact('scales', 'components', 'counts');
    }

    private function applyScales(array $items, int $schoolId): void
    {
        foreach ($items as $item) {
            $source = DB::table('grading_schemes')->where('school_id', $schoolId)->where('id', $item['id'])->first();
            DB::table('grading_schemes')->where('id', $source->id)->update(['section_id' => $item['kept_section_id'], 'updated_at' => now()]);
            $ids = [$item['kept_section_id'] => (int) $source->id];
            foreach ($item['target_section_ids'] as $sectionId) {
                if ($sectionId === $item['kept_section_id']) {
                    continue;
                }
                $copyId = DB::table('grading_schemes')->insertGetId([
                    'school_id' => $schoolId, 'section_id' => $sectionId, 'name' => $source->name,
                    'created_at' => $source->created_at, 'updated_at' => now(),
                ]);
                $ids[$sectionId] = $copyId;
                foreach (DB::table('grade_bands')->where('school_id', $schoolId)->where('grading_scheme_id', $source->id)->get() as $band) {
                    DB::table('grade_bands')->insert([
                        'school_id' => $schoolId, 'grading_scheme_id' => $copyId,
                        'min_score' => $band->min_score, 'max_score' => $band->max_score,
                        'grade' => $band->grade, 'remark' => $band->remark,
                        'created_at' => $band->created_at, 'updated_at' => now(),
                    ]);
                }
            }
            foreach ($ids as $sectionId => $scaleId) {
                DB::table('result_templates')->where('school_id', $schoolId)
                    ->where('grading_scheme_id', $source->id)
                    ->whereIn('id', $this->templateIdsForSection($schoolId, $sectionId))
                    ->update(['grading_scheme_id' => $scaleId, 'updated_at' => now()]);
            }
        }
    }

    private function applyComponents(array $items, int $schoolId): void
    {
        foreach ($items as $item) {
            $source = DB::table('assessment_components')->where('school_id', $schoolId)->where('id', $item['id'])->first();
            DB::table('assessment_components')->where('id', $source->id)->update(['section_id' => $item['kept_section_id'], 'updated_at' => now()]);
            $ids = [$item['kept_section_id'] => (int) $source->id];
            foreach ($item['target_section_ids'] as $sectionId) {
                if ($sectionId === $item['kept_section_id']) {
                    continue;
                }
                $ids[$sectionId] = DB::table('assessment_components')->insertGetId([
                    'school_id' => $schoolId, 'section_id' => $sectionId, 'name' => $source->name,
                    'max_score' => $source->max_score, 'created_at' => $source->created_at, 'updated_at' => now(),
                ]);
            }
            foreach ($ids as $sectionId => $componentId) {
                $templateIds = $this->templateIdsForSection($schoolId, $sectionId);
                DB::table('result_template_components')->where('school_id', $schoolId)
                    ->where('assessment_component_id', $source->id)->whereIn('result_template_id', $templateIds)
                    ->update(['assessment_component_id' => $componentId, 'updated_at' => now()]);
                $resultIds = DB::table('results')->where('school_id', $schoolId)->whereIn('result_template_id', $templateIds)->pluck('id');
                DB::table('result_items')->where('school_id', $schoolId)
                    ->where('assessment_component_id', $source->id)->whereIn('result_id', $resultIds)
                    ->update(['assessment_component_id' => $componentId, 'updated_at' => now()]);
                DB::table('exams')->where('school_id', $schoolId)
                    ->where('assessment_component_id', $source->id)->whereIn('class_id', $this->classIdsForSection($schoolId, $sectionId))
                    ->update(['assessment_component_id' => $componentId, 'updated_at' => now()]);
            }
        }
    }

    private function templateIdsForSection(int $schoolId, int $sectionId): array
    {
        return DB::table('result_templates as rt')->join('academic_levels as l', 'l.id', '=', 'rt.academic_level_id')
            ->where('rt.school_id', $schoolId)->where('l.section_id', $sectionId)->pluck('rt.id')->all();
    }

    private function classIdsForSection(int $schoolId, int $sectionId): array
    {
        return DB::table('classes as c')->join('academic_levels as l', 'l.id', '=', 'c.academic_level_id')
            ->where('c.school_id', $schoolId)->where('l.section_id', $sectionId)->pluck('c.id')->all();
    }

    private function renderPlan(array $plan, bool $dryRun): void
    {
        $this->info($dryRun ? 'DRY RUN — no changes written.' : 'Result configuration Section migration applied.');
        $this->table(['Type', 'Name', 'Used by templates in', 'Will exist in'], collect([
            ...collect($plan['scales'])->map(fn (array $row): array => ['Grade scale', $row['name'], $row['used_section_ids'] === [] ? 'Unused' : implode(', ', $row['used_section_ids']), implode(', ', $row['target_names'])]),
            ...collect($plan['components'])->map(fn (array $row): array => ['Component', $row['name'], $row['used_section_ids'] === [] ? 'Unused' : implode(', ', $row['used_section_ids']), implode(', ', $row['target_names'])]),
        ])->all());
        $this->table(['Change', 'Count'], collect($plan['counts'])->map(
            fn (int $count, string $label): array => [str_replace('_', ' ', $label), $count],
        )->values()->all());
    }
}
