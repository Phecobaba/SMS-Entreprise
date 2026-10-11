<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicConfigurationController extends Controller
{
    public function index(Request $request, SectionAccess $sections): View
    {
        $requestedSection = $request->integer('section') ?: null;
        $levels = $sections->scopeLevels(AcademicLevel::query(), $request->user(), $requestedSection);
        $classes = $sections->scopeClasses(SchoolClass::query(), $request->user(), $requestedSection);
        $arms = $sections->scopeArms(ClassArm::query(), $request->user(), $requestedSection);
        $visibleClassIds = (clone $classes)->pluck('id');

        return view('academics.configuration', [
            'levels' => (clone $levels)->orderBy('name')->paginate(15, ['*'], 'levels_page'),
            'sessions' => AcademicSession::orderByDesc('start_date')->paginate(15, ['*'], 'sessions_page'),
            'terms' => AcademicTerm::with('session')->orderByDesc('start_date')->paginate(15, ['*'], 'terms_page'),
            'classes' => (clone $classes)->with(['level.section', 'department'])->orderBy('name')->paginate(15, ['*'], 'classes_page'),
            'arms' => (clone $arms)->with('schoolClass')->orderBy('name')->paginate(15, ['*'], 'arms_page'),
            'subjects' => Subject::orderBy('name')->paginate(15, ['*'], 'subjects_page'),
            'assignments' => ClassSubject::with(['schoolClass', 'subject'])->whereIn('class_id', $visibleClassIds)->latest()->paginate(15, ['*'], 'assignments_page'),
            'levelOptions' => (clone $levels)->orderBy('name')->get(),
            'sessionOptions' => AcademicSession::orderByDesc('start_date')->get(),
            'classOptions' => (clone $classes)->with('level')->orderBy('name')->get(),
            'subjectOptions' => Subject::orderBy('name')->get(),
        ]);
    }

    public function storeLevel(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_levels')->where('school_id', $context->id())],
        ]);

        AcademicLevel::create($data);

        return back()->with('status', 'Academic level added.');
    }

    public function storeSession(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_sessions')->where('school_id', $context->id())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        AcademicSession::create($data);

        return back()->with('status', 'Academic session added.');
    }

    public function storeTerm(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('terms')->where('school_id', $context->id())->where('session_id', $request->input('session_id'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $session = AcademicSession::findOrFail($data['session_id']);
        if (CarbonImmutable::parse($data['start_date'])->lt($session->start_date)
            || CarbonImmutable::parse($data['end_date'])->gt($session->end_date)) {
            throw ValidationException::withMessages(['start_date' => 'The term dates must fall within the selected session.']);
        }

        AcademicTerm::create($data);

        return back()->with('status', 'Term added.');
    }

    public function storeClass(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'academic_level_id' => ['required', 'integer', Rule::exists('academic_levels', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('classes')->where('school_id', $context->id())->where('academic_level_id', $request->input('academic_level_id'))],
        ]);

        AcademicLevel::findOrFail($data['academic_level_id']);
        SchoolClass::create($data);

        return back()->with('status', 'Class added.');
    }

    public function storeArm(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('class_arms')->where('school_id', $context->id())->where('class_id', $request->input('class_id'))],
        ]);

        SchoolClass::findOrFail($data['class_id']);
        ClassArm::create($data);

        return back()->with('status', 'Class arm added.');
    }

    public function storeSubject(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects')->where('school_id', $context->id())],
        ]);

        Subject::create($data);

        return back()->with('status', 'Subject added.');
    }

    public function attachSubject(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $context->id())],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('school_id', $context->id())],
        ]);

        SchoolClass::findOrFail($data['class_id']);
        Subject::findOrFail($data['subject_id']);
        ClassSubject::firstOrCreate($data);

        return back()->with('status', 'Subject assigned to class.');
    }
}
