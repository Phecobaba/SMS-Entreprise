<?php

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Core\TenantContext;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradingScheme;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Services\ResultConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResultConfigurationController extends Controller
{
    public function index(Request $request, ResultAcademicCatalog $catalog, ResultTeachingAssignments $assignments, SchoolUserDirectory $users): View
    {
        return view('results.configuration', [
            'components' => AssessmentComponent::orderBy('name')->paginate(15, ['*'], 'components_page'),
            'schemes' => GradingScheme::with('bands')->orderBy('name')->paginate(15, ['*'], 'schemes_page'),
            'templates' => ResultTemplate::with(['components.component', 'scheme'])->latest()->paginate(15, ['*'], 'templates_page'),
            'componentOptions' => AssessmentComponent::orderBy('name')->get(),
            'schemeOptions' => GradingScheme::orderBy('name')->get(),
            'teacherAssignments' => $assignments->paginated(),
            'userOptions' => $users->activeOptions(),
            ...$catalog->options($request->user()),
        ]);
    }

    public function assignTeacher(Request $request, ResultTeachingAssignments $assignments): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'class_id' => ['required', 'integer'], 'subject_id' => ['required', 'integer']]);
        app(SectionAccess::class)->assertClass($request->user(), (int) $data['class_id']);
        $assignments->assign((int) $data['user_id'], (int) $data['class_id'], (int) $data['subject_id']);

        return back()->with('status', 'Teacher assigned to the class subject.');
    }

    public function component(Request $request, ResultConfiguration $configuration, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('assessment_components')->where('school_id', $context->id())],
            'max_score' => ['required', 'numeric', 'gt:0', 'lte:100'],
        ]);
        $configuration->addComponent($data['name'], (float) $data['max_score']);

        return back()->with('status', 'Assessment component added.');
    }

    public function scheme(Request $request, ResultConfiguration $configuration, TenantContext $context): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('grading_schemes')->where('school_id', $context->id())]]);
        $configuration->addScheme($data['name']);

        return back()->with('status', 'Grading scheme added.');
    }

    public function band(Request $request, ResultConfiguration $configuration): RedirectResponse
    {
        $data = $request->validate([
            'grading_scheme_id' => ['required', 'integer'],
            'min_score' => ['required', 'numeric', 'between:0,100'],
            'max_score' => ['required', 'numeric', 'between:0,100', 'gte:min_score'],
            'grade' => ['required', 'string', 'max:20'],
            'remark' => ['nullable', 'string', 'max:100'],
        ]);
        $configuration->addBand((int) $data['grading_scheme_id'], (float) $data['min_score'], (float) $data['max_score'], $data['grade'], $data['remark'] ?? null);

        return back()->with('status', 'Grade band added.');
    }

    public function template(Request $request, ResultConfiguration $configuration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'academic_level_id' => ['required', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'grading_scheme_id' => ['required', 'integer'],
            'components' => ['required', 'array', 'min:1'],
            'components.*' => ['required', 'integer'],
            'show_subject_remarks' => ['sometimes', 'boolean'],
        ]);
        app(SectionAccess::class)->assertLevel($request->user(), (int) $data['academic_level_id']);
        $configuration->addTemplate($data['name'], (int) $data['academic_level_id'], isset($data['class_id']) ? (int) $data['class_id'] : null, (int) $data['grading_scheme_id'], $data['components'], (bool) ($data['show_subject_remarks'] ?? true));

        return back()->with('status', 'Result template added.');
    }

    public function subjectRemarks(int $template, Request $request, ResultConfiguration $configuration): RedirectResponse
    {
        $data = $request->validate(['show_subject_remarks' => ['required', 'boolean']]);
        $configuration->setSubjectRemarks($template, (bool) $data['show_subject_remarks']);

        return back()->with('status', 'Subject remarks setting saved.');
    }
}
