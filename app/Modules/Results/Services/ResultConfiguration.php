<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Models\ResultTemplateComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultConfiguration
{
    public function addComponent(int $sectionId, string $name, float $maxScore): AssessmentComponent
    {
        if ($maxScore <= 0 || $maxScore > 100) {
            throw ValidationException::withMessages(['max_score' => 'The maximum score must be greater than zero and at most 100.']);
        }

        return AssessmentComponent::create(['section_id' => $sectionId, 'name' => $name, 'max_score' => $maxScore]);
    }

    public function addScheme(int $sectionId, string $name): GradingScheme
    {
        return GradingScheme::create(['section_id' => $sectionId, 'name' => $name]);
    }

    public function addBand(int $schemeId, int $min, int $max, string $grade, ?string $remark): GradeBand
    {
        $scheme = GradingScheme::findOrFail($schemeId);
        $bands = GradeBand::where('grading_scheme_id', $scheme->id)->get()
            ->map(fn (GradeBand $band): array => $band->only(['min_score', 'max_score']))->all();
        $bands[] = ['min_score' => $min, 'max_score' => $max];
        app(GradeScale::class)->assertValidBands($bands, 'min_score');

        return GradeBand::create(['grading_scheme_id' => $scheme->id, 'min_score' => $min, 'max_score' => $max, 'grade' => $grade, 'remark' => $remark]);
    }

    public function addTemplate(string $name, int $sectionId, ?int $levelId, ?int $classId, int $schemeId, array $componentIds, bool $showSubjectRemarks = true, bool $showOverallTotal = true, bool $showAverage = true, bool $showPosition = true): ResultTemplate
    {
        app(ResultAcademicCatalog::class)->assertTemplateScope($sectionId, $levelId, $classId);
        $this->assertTemplateConfiguration($sectionId, $schemeId, $componentIds);
        $scopeKey = $this->scopeKey($sectionId, $levelId, $classId);
        if (ResultTemplate::where('active_scope_key', $scopeKey)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'A result template already exists for this scope.']);
        }
        $ids = array_values(array_unique(array_map('intval', $componentIds)));
        if (count($ids) === 0 || count($ids) !== count($componentIds)) {
            throw ValidationException::withMessages(['components' => 'Choose one or more distinct assessment components.']);
        }

        return DB::transaction(function () use ($name, $sectionId, $levelId, $classId, $schemeId, $scopeKey, $ids, $showSubjectRemarks, $showOverallTotal, $showAverage, $showPosition): ResultTemplate {
            $template = ResultTemplate::create([
                'name' => $name,
                'section_id' => $sectionId,
                'academic_level_id' => $levelId,
                'class_id' => $classId,
                'grading_scheme_id' => $schemeId,
                'scope_key' => $scopeKey,
                'active_scope_key' => $scopeKey,
                'show_subject_remarks' => $showSubjectRemarks,
                'show_overall_total' => $showOverallTotal,
                'show_average' => $showAverage,
                'show_position' => $showPosition,
            ]);
            foreach ($ids as $position => $id) {
                ResultTemplateComponent::create(['result_template_id' => $template->id, 'assessment_component_id' => $id, 'position' => $position + 1]);
            }

            return $template;
        });
    }

    public function setSubjectRemarks(int $templateId, bool $show): ResultTemplate
    {
        $template = ResultTemplate::findOrFail($templateId);
        $this->assertTemplateActive($template);
        $template->update(['show_subject_remarks' => $show]);

        return $template;
    }

    public function setDisplayOptions(int $templateId, array $options): ResultTemplate
    {
        $template = ResultTemplate::findOrFail($templateId);
        $this->assertTemplateActive($template);
        $template->update([
            'show_subject_remarks' => (bool) ($options['show_subject_remarks'] ?? $template->show_subject_remarks),
            'show_overall_total' => (bool) ($options['show_overall_total'] ?? $template->show_overall_total),
            'show_average' => (bool) ($options['show_average'] ?? $template->show_average),
            'show_position' => (bool) ($options['show_position'] ?? $template->show_position),
        ]);

        return $template;
    }

    public function updateComponent(AssessmentComponent $component, string $name, float $maxScore, User $actor): AssessmentComponent
    {
        $this->assertComponentUnlocked($component);
        if ($maxScore <= 0 || $maxScore > 100) {
            throw ValidationException::withMessages(['max_score' => 'The maximum score must be greater than zero and at most 100.']);
        }
        $before = $component->only(['name', 'max_score']);
        $component->update(['name' => trim($name), 'max_score' => $maxScore]);
        app(AuditRecorder::class)->record($actor, 'assessment_component_updated', $component, [
            'before' => $before, 'after' => $component->only(['name', 'max_score']),
        ]);

        return $component;
    }

    public function deleteComponent(AssessmentComponent $component, User $actor): void
    {
        $results = $this->componentResultCount($component);
        if ($results > 0) {
            $this->throwLocked($results, 'component');
        }
        $templates = ResultTemplateComponent::where('assessment_component_id', $component->id)->count();
        if ($templates > 0) {
            throw ValidationException::withMessages([
                'component' => "{$component->name} is used by {$templates} result ".str('template')->plural($templates)." and can't be deleted.",
            ]);
        }
        $before = $component->only(['name', 'max_score']);
        app(AuditRecorder::class)->record($actor, 'assessment_component_deleted', $component, ['before' => $before, 'after' => null]);
        $component->delete();
    }

    public function updateScheme(GradingScheme $scheme, string $name, User $actor): GradingScheme
    {
        $this->assertSchemeUnlocked($scheme);
        $before = $scheme->only(['name']);
        $scheme->update(['name' => trim($name)]);
        app(AuditRecorder::class)->record($actor, 'grading_scheme_updated', $scheme, [
            'before' => $before, 'after' => $scheme->only(['name']),
        ]);

        return $scheme;
    }

    public function deleteScheme(GradingScheme $scheme, User $actor): void
    {
        $this->assertSchemeUnlocked($scheme);
        $templates = ResultTemplate::where('grading_scheme_id', $scheme->id)->count();
        if ($templates > 0) {
            throw ValidationException::withMessages([
                'scheme' => "{$scheme->name} is used by {$templates} result ".str('template')->plural($templates)." and can't be deleted.",
            ]);
        }
        $before = $scheme->only(['name']);
        app(AuditRecorder::class)->record($actor, 'grading_scheme_deleted', $scheme, ['before' => $before, 'after' => null]);
        $scheme->delete();
    }

    public function updateBand(GradeBand $band, int $min, int $max, string $grade, ?string $remark, User $actor): GradeBand
    {
        $this->assertBandUnlocked($band);
        $current = GradeBand::where('grading_scheme_id', $band->grading_scheme_id)->orderBy('min_score')->get();
        $candidate = $current->map(fn (GradeBand $row): array => $row->id === $band->id
            ? ['min_score' => $min, 'max_score' => $max]
            : $row->only(['min_score', 'max_score']))->all();
        app(GradeScale::class)->assertValidBands($candidate, 'min_score');
        $before = $band->only(['grading_scheme_id', 'min_score', 'max_score', 'grade', 'remark']);
        GradeBand::whereKey($band->id)->update([
            'min_score' => $min,
            'max_score' => $max,
            'grade' => trim($grade),
            'remark' => $remark === null ? null : trim($remark),
        ]);
        $band->refresh();
        app(AuditRecorder::class)->record($actor, 'grade_band_updated', $band, [
            'before' => $before, 'after' => $band->only(['grading_scheme_id', 'min_score', 'max_score', 'grade', 'remark']),
        ]);

        return $band;
    }

    public function deleteBand(GradeBand $band, User $actor): void
    {
        $this->assertBandUnlocked($band);
        $before = $band->only(['grading_scheme_id', 'min_score', 'max_score', 'grade', 'remark']);
        app(AuditRecorder::class)->record($actor, 'grade_band_deleted', $band, ['before' => $before, 'after' => null]);
        $band->delete();
    }

    public function updateTemplate(ResultTemplate $template, string $name, ?int $levelId, ?int $classId, int $schemeId, array $componentIds, User $actor): ResultTemplate
    {
        $this->assertTemplateUnlocked($template);
        $sectionId = (int) $template->section_id;
        app(ResultAcademicCatalog::class)->assertTemplateScope($sectionId, $levelId, $classId);
        $this->assertTemplateConfiguration($sectionId, $schemeId, $componentIds);
        $scopeKey = $this->scopeKey($sectionId, $levelId, $classId);
        if (ResultTemplate::where('active_scope_key', $scopeKey)->whereKeyNot($template->id)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'A result template already exists for this scope.']);
        }
        $ids = array_values(array_unique(array_map('intval', $componentIds)));
        if ($ids === [] || count($ids) !== count($componentIds)) {
            throw ValidationException::withMessages(['components' => 'Choose one or more distinct assessment components from this Section.']);
        }
        $before = $template->only(['name', 'academic_level_id', 'class_id', 'grading_scheme_id', 'scope_key']);
        DB::transaction(function () use ($template, $name, $levelId, $classId, $schemeId, $scopeKey, $ids, $actor, $before): void {
            $template->update([
                'name' => trim($name), 'academic_level_id' => $levelId, 'class_id' => $classId,
                'grading_scheme_id' => $schemeId, 'scope_key' => $scopeKey, 'active_scope_key' => $scopeKey,
            ]);
            ResultTemplateComponent::where('result_template_id', $template->id)->delete();
            foreach ($ids as $position => $id) {
                ResultTemplateComponent::create(['result_template_id' => $template->id, 'assessment_component_id' => $id, 'position' => $position + 1]);
            }
            app(AuditRecorder::class)->record($actor, 'result_template_updated', $template, [
                'before' => $before,
                'after' => $template->only(['name', 'academic_level_id', 'class_id', 'grading_scheme_id', 'scope_key']),
                'component_ids' => $ids,
            ]);
        });

        return $template->refresh();
    }

    public function deleteTemplate(ResultTemplate $template, User $actor): void
    {
        $this->assertTemplateUnlocked($template);
        $before = $template->only(['name', 'academic_level_id', 'class_id', 'grading_scheme_id', 'scope_key', 'show_subject_remarks']);
        app(AuditRecorder::class)->record($actor, 'result_template_deleted', $template, ['before' => $before, 'after' => null]);
        $template->delete();
    }

    public function retireTemplate(ResultTemplate $template, User $actor): ResultTemplate
    {
        return DB::transaction(function () use ($template, $actor): ResultTemplate {
            $template = ResultTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();
            $this->assertTemplateActive($template);
            $count = $this->templateResultCount($template);
            if ($count === 0) {
                throw ValidationException::withMessages([
                    'template' => 'No results use this template. Delete it instead.',
                ]);
            }
            $currentCount = $this->currentTermResultCount($template);
            if ($currentCount > 0) {
                throw ValidationException::withMessages([
                    'template' => "{$currentCount} results in the current term use this template. Retire it between terms.",
                ]);
            }

            $template->update([
                'active_scope_key' => null,
                'retired_at' => now(),
                'retired_by' => $actor->id,
            ]);
            app(AuditRecorder::class)->record($actor, 'result_template_retired', $template, [
                'result_count' => $count,
            ]);

            return $template->refresh();
        });
    }

    public function templateResultCount(ResultTemplate $template): int
    {
        return Result::where('result_template_id', $template->id)->count();
    }

    public function currentTermResultCount(ResultTemplate $template): int
    {
        $term = app(AcademicCalendar::class)->currentTerm();

        return $term === null
            ? 0
            : Result::where('result_template_id', $template->id)->where('term_id', $term->id)->count();
    }

    public function broaderCurrentTermWarning(int $sectionId, ?int $levelId, ?int $classId): ?array
    {
        $term = app(AcademicCalendar::class)->currentTerm();
        if ($term === null || $levelId === null) {
            return null;
        }

        $broader = ResultTemplate::whereNull('retired_at')->where('section_id', $sectionId);
        if ($classId !== null) {
            $broader->where(function ($query) use ($levelId): void {
                $query->where(function ($levelScope) use ($levelId): void {
                    $levelScope->whereNull('class_id')->where('academic_level_id', $levelId);
                })->orWhere(function ($sectionScope): void {
                    $sectionScope->whereNull('class_id')->whereNull('academic_level_id');
                });
            })->orderByRaw('case when academic_level_id is not null then 1 else 2 end');
        } else {
            $broader->whereNull('class_id')->whereNull('academic_level_id');
        }
        $template = $broader->first();
        if (! $template) {
            return null;
        }

        $results = Result::where('result_template_id', $template->id)->where('term_id', $term->id)
            ->whereHas('classArm.schoolClass', fn ($query) => $classId !== null
                ? $query->whereKey($classId)
                : $query->where('academic_level_id', $levelId));
        $pupils = $results->distinct()->count('student_id');

        return $pupils > 0 ? ['pupils' => $pupils, 'template' => $template->name] : null;
    }

    public function bandResultCount(GradeBand $band): int
    {
        return Result::whereHas('template', fn ($query) => $query->where('grading_scheme_id', $band->grading_scheme_id))->count();
    }

    public function schemeResultCount(GradingScheme $scheme): int
    {
        return Result::whereHas('template', fn ($query) => $query->where('grading_scheme_id', $scheme->id))->count();
    }

    public function componentResultCount(AssessmentComponent $component): int
    {
        return ResultItem::where('assessment_component_id', $component->id)->distinct()->count('result_id');
    }

    private function assertTemplateConfiguration(int $sectionId, int $schemeId, array $componentIds): void
    {
        if (! GradingScheme::whereKey($schemeId)->where('section_id', $sectionId)->exists()) {
            throw ValidationException::withMessages(['grading_scheme_id' => 'Choose a Grade scale from the template’s Section.']);
        }
        $ids = array_values(array_unique(array_map('intval', $componentIds)));
        if ($ids === [] || count($ids) !== count($componentIds)
            || AssessmentComponent::whereIn('id', $ids)->where('section_id', $sectionId)->count() !== count($ids)) {
            throw ValidationException::withMessages(['components' => 'Choose distinct assessment components from the template’s Section.']);
        }
    }

    private function scopeKey(int $sectionId, ?int $levelId, ?int $classId): string
    {
        return $classId !== null
            ? 'class:'.$classId
            : ($levelId !== null ? 'level:'.$levelId : 'section:'.$sectionId);
    }

    private function assertTemplateUnlocked(ResultTemplate $template): void
    {
        $this->assertTemplateActive($template);
        $count = $this->templateResultCount($template);
        if ($count > 0) {
            throw ValidationException::withMessages([
                'template' => "Locked because {$count} results use it. Retire it between terms and create a new one.",
            ]);
        }
    }

    private function assertTemplateActive(ResultTemplate $template): void
    {
        if ($template->retired_at !== null) {
            throw ValidationException::withMessages([
                'template' => 'A retired template is read-only and cannot be changed or restored.',
            ]);
        }
    }

    private function assertBandUnlocked(GradeBand $band): void
    {
        $count = $this->bandResultCount($band);
        if ($count > 0) {
            $this->throwLocked($count, 'band');
        }
    }

    private function assertSchemeUnlocked(GradingScheme $scheme): void
    {
        $count = $this->schemeResultCount($scheme);
        if ($count > 0) {
            $this->throwLocked($count, 'scheme');
        }
    }

    private function assertComponentUnlocked(AssessmentComponent $component): void
    {
        $count = $this->componentResultCount($component);
        if ($count > 0) {
            $this->throwLocked($count, 'component');
        }
    }

    private function throwLocked(int $count, string $field): never
    {
        throw ValidationException::withMessages([
            $field => "This is locked because {$count} results use it. Create a new template for later terms.",
        ]);
    }
}
