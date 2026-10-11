<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Models\ResultTemplateComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultConfiguration
{
    public function addComponent(string $name, float $maxScore): AssessmentComponent
    {
        if ($maxScore <= 0 || $maxScore > 100) {
            throw ValidationException::withMessages(['max_score' => 'The maximum score must be greater than zero and at most 100.']);
        }

        return AssessmentComponent::create(['name' => $name, 'max_score' => $maxScore]);
    }

    public function addScheme(string $name): GradingScheme
    {
        return GradingScheme::create(['name' => $name]);
    }

    public function addBand(int $schemeId, float $min, float $max, string $grade, ?string $remark): GradeBand
    {
        $scheme = GradingScheme::findOrFail($schemeId);
        if ($min < 0 || $max > 100 || $min > $max) {
            throw ValidationException::withMessages(['min_score' => 'Grade bounds must be between 0 and 100, with minimum no greater than maximum.']);
        }
        if (GradeBand::where('grading_scheme_id', $scheme->id)->where('min_score', '<=', $max)->where('max_score', '>=', $min)->exists()) {
            throw ValidationException::withMessages(['min_score' => 'This grade band overlaps an existing band.']);
        }

        return GradeBand::create(['grading_scheme_id' => $scheme->id, 'min_score' => $min, 'max_score' => $max, 'grade' => $grade, 'remark' => $remark]);
    }

    public function addTemplate(string $name, int $levelId, ?int $classId, int $schemeId, array $componentIds, bool $showSubjectRemarks = true): ResultTemplate
    {
        app(ResultAcademicCatalog::class)->assertTemplateScope($levelId, $classId);
        GradingScheme::findOrFail($schemeId);
        $scopeKey = $classId === null ? 'level:'.$levelId : 'class:'.$classId;
        if (ResultTemplate::where('scope_key', $scopeKey)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'A result template already exists for this level or class.']);
        }
        $ids = array_values(array_unique(array_map('intval', $componentIds)));
        if (count($ids) === 0 || count($ids) !== count($componentIds)) {
            throw ValidationException::withMessages(['components' => 'Choose one or more distinct assessment components.']);
        }
        if (AssessmentComponent::whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['components' => 'Choose assessment components from this school.']);
        }

        return DB::transaction(function () use ($name, $levelId, $classId, $schemeId, $scopeKey, $ids, $showSubjectRemarks): ResultTemplate {
            $template = ResultTemplate::create([
                'name' => $name,
                'academic_level_id' => $levelId,
                'class_id' => $classId,
                'grading_scheme_id' => $schemeId,
                'scope_key' => $scopeKey,
                'show_subject_remarks' => $showSubjectRemarks,
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
        $template->update(['show_subject_remarks' => $show]);

        return $template;
    }
}
