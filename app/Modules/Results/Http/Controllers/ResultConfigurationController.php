<?php

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\CommentRange;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Services\GradeScale;
use App\Modules\Results\Services\ResultCards;
use App\Modules\Results\Services\ResultConfiguration;
use App\Modules\Results\Services\ResultConfigurationAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResultConfigurationController extends Controller
{
    public function index(Request $request, ResultAcademicCatalog $catalog, GradeScale $gradeScale, ResultConfigurationAccess $access, AcademicCalendar $calendar): View
    {
        $actor = $request->user();
        $requestedSectionId = $request->filled('section') ? (int) $request->query('section') : null;
        $selectedSectionId = $access->selectedSectionId($actor, $requestedSectionId);
        $isAdmin = $access->isAdmin($actor);
        $locked = ! $isAdmin && $selectedSectionId === null;
        $components = AssessmentComponent::with('section')->withCount(['resultItems', 'templateEntries'])->orderBy('name');
        $schemes = GradingScheme::with(['bands', 'section'])->withCount('templates')->orderBy('name');
        $currentTerm = $calendar->currentTerm();
        $templates = ResultTemplate::with(['components.component', 'scheme.section', 'section', 'level.section', 'schoolClass', 'retiredBy'])
            ->withCount([
                'results',
                'results as current_term_results_count' => fn (Builder $query) => $currentTerm
                    ? $query->where('term_id', $currentTerm->id)
                    : $query->whereRaw('1 = 0'),
            ])->latest();
        if ($selectedSectionId !== null) {
            $components->where('section_id', $selectedSectionId);
            $schemes->where('section_id', $selectedSectionId);
            $templates->where('section_id', $selectedSectionId);
        }
        if ($locked) {
            $components->whereRaw('1 = 0');
            $schemes->whereRaw('1 = 0');
            $templates->whereRaw('1 = 0');
        }

        $visibleSchemeIds = (clone $schemes)->pluck('id');
        $schemeResultCounts = Result::query()->join('result_templates', function ($join): void {
            $join->on('result_templates.id', '=', 'results.result_template_id')
                ->on('result_templates.school_id', '=', 'results.school_id');
        })->whereIn('result_templates.grading_scheme_id', $visibleSchemeIds)
            ->selectRaw('result_templates.grading_scheme_id, count(results.id) as aggregate')
            ->groupBy('result_templates.grading_scheme_id')->pluck('aggregate', 'result_templates.grading_scheme_id');
        $schemeOptions = GradingScheme::with('section')->when($selectedSectionId, fn (Builder $query) => $query->where('section_id', $selectedSectionId))->orderBy('name')->get();
        $componentOptions = AssessmentComponent::with('section')->when($selectedSectionId, fn (Builder $query) => $query->where('section_id', $selectedSectionId))->orderBy('name')->get();
        $coverageSchemes = (clone $schemes)->with('bands')->get();

        return view('results.configuration', [
            'components' => $components->paginate(15, ['*'], 'components_page')->withQueryString(),
            'schemes' => $schemes->paginate(15, ['*'], 'schemes_page')->withQueryString(),
            'templates' => (clone $templates)->whereNull('retired_at')->paginate(15, ['*'], 'templates_page')->withQueryString(),
            'retiredTemplates' => (clone $templates)->whereNotNull('retired_at')->paginate(15, ['*'], 'retired_templates_page')->withQueryString(),
            'schemeResultCounts' => $schemeResultCounts,
            'schemeCoverage' => $coverageSchemes->mapWithKeys(fn (GradingScheme $scheme): array => [$scheme->id => $gradeScale->coverageFor($scheme->bands->sortBy('min_score')->values())]),
            'componentOptions' => $componentOptions,
            'schemeOptions' => $schemeOptions,
            'sections' => Section::orderBy('name')->get(),
            'selectedSectionId' => $selectedSectionId,
            'selectedSection' => $selectedSectionId ? Section::find($selectedSectionId) : null,
            'isAdmin' => $isAdmin,
            'locked' => $locked,
            'commentRanges' => $selectedSectionId ? CommentRange::where('section_id', $selectedSectionId)->orderBy('comment_type')->orderBy('min_average')->get() : collect(),
            'proprietorCommentTitle' => SchoolSetting::query()->value('proprietor_comment_title') ?: "Proprietor's comment",
            ...$catalog->options($actor, $selectedSectionId),
        ]);
    }

    public function assignTeacher(Request $request, ResultTeachingAssignments $assignments): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'class_id' => ['required', 'integer'], 'subject_id' => ['required', 'integer']]);
        app(SectionAccess::class)->assertClass($request->user(), (int) $data['class_id']);
        $assignments->assign((int) $data['user_id'], (int) $data['class_id'], (int) $data['subject_id'], $request->user());

        return redirect()->route('results.configuration')->with('status', 'Teacher assignment saved.');
    }

    public function component(Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access, TenantContext $context): RedirectResponse
    {
        $sectionId = $access->createSectionId($request->user(), $request->input('section_id'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('assessment_components')->where(fn ($query) => $query->where('school_id', $context->id())->where('section_id', $sectionId))],
            'max_score' => ['required', 'numeric', 'gt:0', 'lte:100'],
        ]);
        $configuration->addComponent($sectionId, $data['name'], (float) $data['max_score']);

        return redirect()->route('results.configuration')->with('status', 'Assessment component saved.');
    }

    public function updateComponent(AssessmentComponent $component, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access, TenantContext $context): RedirectResponse
    {
        $access->assertComponent($request->user(), $component);
        $access->assertImmutableSection($request->input('section_id'), (int) $component->section_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('assessment_components')->where(fn ($query) => $query->where('school_id', $context->id())->where('section_id', $component->section_id))->ignore($component->id)],
            'max_score' => ['required', 'numeric', 'gt:0', 'lte:100'],
        ]);
        $configuration->updateComponent($component, $data['name'], (float) $data['max_score'], $request->user());

        return redirect()->route('results.configuration')->with('status', 'Assessment component saved.');
    }

    public function destroyComponent(AssessmentComponent $component, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertComponent($request->user(), $component);
        $configuration->deleteComponent($component, $request->user());

        return redirect()->route('results.configuration')->with('status', 'Assessment component deleted.');
    }

    public function scheme(Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access, TenantContext $context): RedirectResponse
    {
        $sectionId = $access->createSectionId($request->user(), $request->input('section_id'));
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('grading_schemes')->where(fn ($query) => $query->where('school_id', $context->id())->where('section_id', $sectionId))]]);
        $configuration->addScheme($sectionId, $data['name']);

        return redirect()->route('results.configuration')->with('status', 'Grade scale saved.');
    }

    public function updateScheme(GradingScheme $scheme, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access, TenantContext $context): RedirectResponse
    {
        $access->assertScheme($request->user(), $scheme);
        $access->assertImmutableSection($request->input('section_id'), (int) $scheme->section_id);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('grading_schemes')->where(fn ($query) => $query->where('school_id', $context->id())->where('section_id', $scheme->section_id))->ignore($scheme->id)]]);
        $configuration->updateScheme($scheme, $data['name'], $request->user());

        return redirect()->route('results.configuration')->with('status', 'Grade scale saved.');
    }

    public function destroyScheme(GradingScheme $scheme, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertScheme($request->user(), $scheme);
        $configuration->deleteScheme($scheme, $request->user());

        return redirect()->route('results.configuration')->with('status', 'Grade scale deleted.');
    }

    public function band(Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $data = $this->bandData($request);
        $scheme = GradingScheme::findOrFail((int) $data['grading_scheme_id']);
        $access->assertScheme($request->user(), $scheme);
        $configuration->addBand($scheme->id, (int) $data['min_score'], (int) $data['max_score'], $data['grade'], $data['remark'] ?? null);

        return redirect()->route('results.configuration')->with('status', 'Grade band saved.');
    }

    public function updateBand(GradeBand $band, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertBand($request->user(), $band);
        $data = $this->bandData($request, false);
        $configuration->updateBand($band, (int) $data['min_score'], (int) $data['max_score'], $data['grade'], $data['remark'] ?? null, $request->user());

        return redirect()->route('results.configuration')->with('status', 'Grade band saved.');
    }

    public function destroyBand(GradeBand $band, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertBand($request->user(), $band);
        $configuration->deleteBand($band, $request->user());

        return redirect()->route('results.configuration')->with('status', 'Grade band deleted.');
    }

    public function template(Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $data = $this->templateData($request);
        $sectionId = $access->createSectionId($request->user(), $request->input('section_id'));
        $levelId = isset($data['academic_level_id']) ? (int) $data['academic_level_id'] : null;
        if ($levelId !== null) {
            $this->assertLevelSection($levelId, $sectionId);
            app(SectionAccess::class)->assertLevel($request->user(), $levelId);
        }
        $classId = isset($data['class_id']) ? (int) $data['class_id'] : null;
        $warning = $configuration->broaderCurrentTermWarning($sectionId, $levelId, $classId);
        $configuration->addTemplate(
            $data['name'], $sectionId, $levelId, $classId, (int) $data['grading_scheme_id'], $data['components'],
            (bool) ($data['show_subject_remarks'] ?? true), (bool) ($data['show_overall_total'] ?? true),
            (bool) ($data['show_average'] ?? true), (bool) ($data['show_position'] ?? true),
        );

        $redirect = redirect()->route('results.configuration')->with('status', 'Result template saved.');
        if ($warning) {
            $pupils = $warning['pupils'];
            $redirect->with('warning', "{$pupils} ".str('pupil')->plural($pupils)." already have results this term under {$warning['template']}; they keep it.");
        }

        return $redirect;
    }

    public function updateTemplate(ResultTemplate $template, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertTemplate($request->user(), $template);
        $currentSectionId = $access->templateSectionId($template);
        $access->assertImmutableSection($request->input('section_id'), $currentSectionId);
        $data = $this->templateData($request, false);
        $levelId = isset($data['academic_level_id']) ? (int) $data['academic_level_id'] : null;
        if ($levelId !== null) {
            $this->assertLevelSection($levelId, $currentSectionId);
            app(SectionAccess::class)->assertLevel($request->user(), $levelId);
        }
        $configuration->updateTemplate($template, $data['name'], $levelId, isset($data['class_id']) ? (int) $data['class_id'] : null, (int) $data['grading_scheme_id'], $data['components'], $request->user());

        return redirect()->route('results.configuration')->with('status', 'Result template saved.');
    }

    public function destroyTemplate(ResultTemplate $template, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertTemplate($request->user(), $template);
        $configuration->deleteTemplate($template, $request->user());

        return redirect()->route('results.configuration')->with('status', 'Result template deleted.');
    }

    public function retireTemplate(ResultTemplate $template, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertTemplate($request->user(), $template);
        $configuration->retireTemplate($template, $request->user());

        return redirect()->route('results.configuration')->with('status', 'Result template retired. You can now create its replacement.');
    }

    public function subjectRemarks(ResultTemplate $template, Request $request, ResultConfiguration $configuration, ResultConfigurationAccess $access): RedirectResponse
    {
        $access->assertTemplate($request->user(), $template);
        $data = $request->validate([
            'show_subject_remarks' => ['sometimes', 'boolean'],
            'show_overall_total' => ['sometimes', 'boolean'],
            'show_average' => ['sometimes', 'boolean'],
            'show_position' => ['sometimes', 'boolean'],
        ]);
        $configuration->setDisplayOptions($template->id, $data);

        return redirect()->route('results.configuration')->with('status', 'Report-card display settings saved.');
    }

    public function commentMode(Request $request, ResultCards $cards, ResultConfigurationAccess $access): RedirectResponse
    {
        $data = $request->validate(['section_id' => ['required', 'integer'], 'comment_type' => ['required', 'in:Principal,Proprietor'], 'mode' => ['required', 'in:single,range']]);
        $sectionId = (int) $data['section_id'];
        $access->assertSection($request->user(), $sectionId);
        $cards->setCommentMode(Section::findOrFail($sectionId), $data['comment_type'], $data['mode'], $request->user());

        return redirect()->route('results.configuration', ['section' => $sectionId])->with('status', 'Comment mode saved.');
    }

    public function commentRange(Request $request, ResultCards $cards, ResultConfigurationAccess $access): RedirectResponse
    {
        $data = $this->commentRangeData($request);
        $sectionId = (int) $data['section_id'];
        $access->assertSection($request->user(), $sectionId);
        $cards->saveCommentRange(Section::findOrFail($sectionId), $data['comment_type'], (int) $data['min_average'], (int) $data['max_average'], $data['comment_text'], $request->user());

        return redirect()->route('results.configuration', ['section' => $sectionId])->with('status', 'Comment range saved.');
    }

    public function updateCommentRange(CommentRange $commentRange, Request $request, ResultCards $cards): RedirectResponse
    {
        $data = $request->validate([
            'min_average' => ['required', 'integer', 'between:0,100'],
            'max_average' => ['required', 'integer', 'between:0,100', 'gte:min_average'],
            'comment_text' => ['required', 'string', 'max:320'],
        ]);
        $section = Section::findOrFail($commentRange->section_id);
        $cards->saveCommentRange($section, $commentRange->comment_type, (int) $data['min_average'], (int) $data['max_average'], $data['comment_text'], $request->user(), $commentRange);

        return redirect()->route('results.configuration', ['section' => $section->id])->with('status', 'Comment range saved.');
    }

    public function destroyCommentRange(CommentRange $commentRange, Request $request, ResultCards $cards): RedirectResponse
    {
        $sectionId = (int) $commentRange->section_id;
        $cards->deleteCommentRange($commentRange, $request->user());

        return redirect()->route('results.configuration', ['section' => $sectionId])->with('status', 'Comment range deleted.');
    }

    public function proprietorTitle(Request $request, ResultCards $cards): RedirectResponse
    {
        $data = $request->validate(['proprietor_comment_title' => ['required', 'string', 'max:100']]);
        $cards->setProprietorTitle($data['proprietor_comment_title'], $request->user());

        return redirect()->route('results.configuration')->with('status', 'Proprietor comment title saved.');
    }

    private function bandData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'grading_scheme_id' => [$creating ? 'required' : 'sometimes', 'integer'],
            'min_score' => ['required', 'numeric', 'between:0,100', $this->wholeNumberBound()],
            'max_score' => ['required', 'numeric', 'between:0,100', 'gte:min_score', $this->wholeNumberBound()],
            'grade' => ['required', 'string', 'max:20'],
            'remark' => ['nullable', 'string', 'max:100'],
        ], [
            'min_score.numeric' => 'Grade band bounds must be whole numbers from 0 to 100.',
            'max_score.numeric' => 'Grade band bounds must be whole numbers from 0 to 100.',
        ]);
    }

    private function wholeNumberBound(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_numeric($value) || floor((float) $value) !== (float) $value) {
                $fail('Grade band bounds must be whole numbers from 0 to 100.');
            }
        };
    }

    private function templateData(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'section_id' => ['sometimes', 'integer'],
            'academic_level_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'grading_scheme_id' => ['required', 'integer'],
            'components' => ['required', 'array', 'min:1'],
            'components.*' => ['required', 'integer'],
            'show_subject_remarks' => ['sometimes', 'boolean'],
            'show_overall_total' => ['sometimes', 'boolean'],
            'show_average' => ['sometimes', 'boolean'],
            'show_position' => ['sometimes', 'boolean'],
        ]);
    }

    private function commentRangeData(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'integer'],
            'comment_type' => ['required', 'in:Principal,Proprietor'],
            'min_average' => ['required', 'integer', 'between:0,100'],
            'max_average' => ['required', 'integer', 'between:0,100', 'gte:min_average'],
            'comment_text' => ['required', 'string', 'max:320'],
        ]);
    }

    private function assertLevelSection(int $levelId, int $sectionId): void
    {
        if (! AcademicLevel::whereKey($levelId)->where('section_id', $sectionId)->exists()) {
            throw new AuthorizationException('The template Level must belong to the selected Section.');
        }
    }
}
