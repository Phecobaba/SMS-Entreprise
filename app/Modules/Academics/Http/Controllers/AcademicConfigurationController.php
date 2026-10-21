<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Services\AcademicDeleteGuard;
use App\Modules\Academics\Services\ClassOrderingService;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicConfigurationController extends Controller
{
    public function levels(Request $request, SectionAccess $sections): View
    {
        $actor = $request->user();
        abort_unless($actor->can('manage_academics') || $actor->can('manage_section_levels'), 403);
        $assignedIds = $sections->sectionIds($actor);
        $canViewAll = $actor->can('view_all_sections');
        $query = $sections->scopeLevels(AcademicLevel::with('section')->withCount('classes'), $actor);

        return view('academics.levels', [
            'levels' => $query->orderBy('name')->limit(100)->get(),
            'sections' => Section::when(! $canViewAll, fn ($q) => $q->whereIn('id', $assignedIds))->orderBy('name')->get(),
            'canViewAll' => $canViewAll,
            'hasSection' => $canViewAll || $assignedIds !== [],
        ]);
    }

    public function sessions(): View
    {
        return view('academics.sessions', [
            'sessions' => AcademicSession::withCount('terms')->orderByDesc('start_date')->limit(100)->get(),
            'terms' => AcademicTerm::with('session')->orderByDesc('start_date')->limit(100)->get(),
            'sessionOptions' => AcademicSession::orderByDesc('start_date')->limit(100)->get(),
        ]);
    }

    public function index(): View
    {
        return view('academics.configuration', [
            'subjects' => Subject::orderBy('name')->paginate(15, ['*'], 'subjects_page'),
            'assignments' => ClassSubject::with(['schoolClass', 'subject'])->latest()->paginate(15, ['*'], 'assignments_page'),
            'classOptions' => SchoolClass::with('level')->orderBy('name')->limit(100)->get(),
            'subjectOptions' => Subject::orderBy('name')->get(),
        ]);
    }

    public function storeLevel(Request $request, TenantContext $context, SectionAccess $sections): RedirectResponse
    {
        $this->authorizeLevels($request);
        $data = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_levels')->where('school_id', $context->id())],
        ]);
        $sections->assertSection($request->user(), (int) $data['section_id']);

        AcademicLevel::create(['section_id' => (int) $data['section_id'], 'name' => trim($data['name'])]);

        return back()->with('status', 'Academic level added.');
    }

    public function storeSession(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_sessions')->where('school_id', $context->id())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
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
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        AcademicSession::findOrFail($data['session_id']);

        AcademicTerm::create($data);

        return back()->with('status', 'Term added.');
    }

    public function storeClass(Request $request, TenantContext $context, ClassOrderingService $ordering): RedirectResponse
    {
        abort_unless($ordering->mayManage($request->user()), 403);
        $rules = [
            'academic_level_id' => ['required', 'integer', Rule::exists('academic_levels', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('classes')->where('school_id', $context->id())->where('academic_level_id', $request->input('academic_level_id'))],
        ];
        if (Department::exists()) {
            $rules['department_id'] = ['nullable', 'integer', Rule::exists('departments', 'id')->where('school_id', $context->id())];
            $rules['placement'] = ['nullable', Rule::in(['new', 'same'])];
        }
        $rules['comes_after_id'] = ['nullable', 'integer', Rule::exists('classes', 'id')->where('school_id', $context->id())];
        $data = $request->validate($rules);
        if (! Department::exists()) {
            $data['department_id'] = null;
            $data['placement'] = 'new';
        }
        $data['placement'] ??= 'new';

        $ordering->create($data, $request->user());

        return back()->with('status', 'Class added.');
    }

    public function updateClass(Request $request, SchoolClass $class, TenantContext $context, ClassOrderingService $ordering): RedirectResponse
    {
        abort_unless($ordering->mayManage($request->user()), 403);
        $ordering->assertMayManageClass($request->user(), $class);
        $rules = [
            'academic_level_id' => ['required', 'integer', Rule::exists('academic_levels', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('classes')->where('school_id', $context->id())->where('academic_level_id', $request->input('academic_level_id'))->ignore($class->id)],
        ];
        if (Department::exists()) {
            $rules['department_id'] = ['nullable', 'integer', Rule::exists('departments', 'id')->where('school_id', $context->id())];
            $rules['placement'] = ['nullable', Rule::in(['new', 'same'])];
        }
        $rules['comes_after_id'] = ['nullable', 'integer', Rule::exists('classes', 'id')->where('school_id', $context->id())];
        $data = $request->validate($rules);
        $data['department_id'] = Department::exists() ? ($data['department_id'] ?? null) : null;
        $data['placement'] = Department::exists() ? ($data['placement'] ?? 'new') : 'new';
        $ordering->update($class, $data, $request->user());

        return back()->with('status', 'Class updated.');
    }

    public function storeArm(Request $request, TenantContext $context, SectionAccess $sections): RedirectResponse
    {
        abort_unless($request->user()->can('manage_academics') || $request->user()->can('manage_section_arms'), 403);
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('class_arms')->where('school_id', $context->id())->where('class_id', $request->input('class_id'))],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        $sections->assertClass($request->user(), $class->id);
        ClassArm::create($data);

        return back()->with('status', 'Class arm added.');
    }

    public function updateLevel(Request $request, AcademicLevel $level, TenantContext $context, SectionAccess $sections, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeLevels($request);
        $sections->assertLevel($request->user(), $level->id);
        $rules = ['name' => ['required', 'string', 'max:100', Rule::unique('academic_levels')->where('school_id', $context->id())->ignore($level->id)]];
        if ($request->user()->can('manage_academics')) {
            $rules['section_id'] = ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $context->id())];
        }
        $data = $request->validate($rules);
        $before = $level->only(['section_id', 'name']);
        $after = ['name' => trim($data['name']), 'section_id' => $request->user()->can('manage_academics') ? (int) $data['section_id'] : $level->section_id];
        if ($request->user()->can('manage_academics')) {
            $sections->assertSection($request->user(), $after['section_id']);
        } elseif ($request->has('section_id') && (int) $request->input('section_id') !== (int) $level->section_id) {
            throw new AuthorizationException('A head cannot move a level to another section.');
        }
        $level->update($after);
        $audit->record($request->user(), 'academic_level_updated', $level, ['before' => $before, 'after' => $after]);

        return back()->with('status', 'Level updated. Existing records were not changed.');
    }

    public function destroyLevel(Request $request, AcademicLevel $level, SectionAccess $sections, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeLevels($request);
        $sections->assertLevel($request->user(), $level->id);
        $guard->assertUnused($level->name, $guard->levelUsage($level), 'level');
        $before = $level->only(['section_id', 'name']);
        $audit->record($request->user(), 'academic_level_deleted', $level, ['before' => $before]);
        $level->delete();

        return back()->with('status', 'Level deleted. No school history was removed.');
    }

    public function updateArm(Request $request, ClassArm $arm, TenantContext $context, SectionAccess $sections, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeArms($request, $arm, $sections);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('class_arms')->where('school_id', $context->id())->where('class_id', $arm->class_id)->ignore($arm->id)]]);
        $before = $arm->only(['class_id', 'name']);
        $arm->update(['name' => trim($data['name'])]);
        $audit->record($request->user(), 'class_arm_updated', $arm, ['before' => $before, 'after' => $arm->only(['class_id', 'name'])]);

        return back()->with('status', 'Class arm renamed. Existing records were not changed.');
    }

    public function destroyArm(Request $request, ClassArm $arm, SectionAccess $sections, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeArms($request, $arm, $sections);
        $guard->assertUnused($arm->schoolClass?->name.' '.$arm->name, $guard->armUsage($arm), 'arm');
        $before = $arm->only(['class_id', 'name']);
        $audit->record($request->user(), 'class_arm_deleted', $arm, ['before' => $before]);
        $arm->delete();

        return back()->with('status', 'Class arm deleted. No school history was removed.');
    }

    public function updateSession(Request $request, AcademicSession $session, TenantContext $context, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_sessions')->where('school_id', $context->id())->ignore($session->id)],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date'],
        ]);
        $before = $session->only(['name', 'start_date', 'end_date']);
        $session->update($data);
        $audit->record($request->user(), 'academic_session_updated', $session, ['before' => $before, 'after' => $session->only(['name', 'start_date', 'end_date'])]);

        return back()->with('status', 'Session updated. Existing records were not changed.');
    }

    public function destroySession(Request $request, AcademicSession $session, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->assertNotCurrent($session->start_date, $session->end_date, 'session');
        $guard->assertUnused($session->name, $guard->sessionUsage($session), 'session');
        $session->load('terms');
        foreach ($session->terms as $term) {
            $this->assertNotCurrent($term->start_date, $term->end_date, 'term');
            $guard->assertUnused($term->name, $guard->termUsage($term), 'session');
        }
        $audit->record($request->user(), 'academic_session_deleted', $session, ['before' => $session->only(['name', 'start_date', 'end_date']), 'term_ids' => $session->terms->pluck('id')->all()]);
        $session->delete();

        return back()->with('status', 'Session and its unused terms deleted. Nothing else was removed.');
    }

    public function updateTerm(Request $request, AcademicTerm $term, TenantContext $context, AuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->where('school_id', $context->id())],
            'name' => ['required', 'string', 'max:100', Rule::unique('terms')->where('school_id', $context->id())->where('session_id', $request->input('session_id'))->ignore($term->id)],
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date'],
        ]);
        $before = $term->only(['session_id', 'name', 'start_date', 'end_date']);
        $term->update($data);
        $audit->record($request->user(), 'academic_term_updated', $term, ['before' => $before, 'after' => $term->only(['session_id', 'name', 'start_date', 'end_date'])]);

        return back()->with('status', 'Term updated. Existing records were not changed.');
    }

    public function destroyTerm(Request $request, AcademicTerm $term, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->assertNotCurrent($term->start_date, $term->end_date, 'term');
        $guard->assertUnused($term->name, $guard->termUsage($term), 'term');
        $audit->record($request->user(), 'academic_term_deleted', $term, ['before' => $term->only(['session_id', 'name', 'start_date', 'end_date'])]);
        $term->delete();

        return back()->with('status', 'Term deleted. No school history was removed.');
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

    private function authorizeLevels(Request $request): void
    {
        abort_unless($request->user()->can('manage_academics') || $request->user()->can('manage_section_levels'), 403);
    }

    private function authorizeArms(Request $request, ClassArm $arm, SectionAccess $sections): void
    {
        abort_unless($request->user()->can('manage_academics') || $request->user()->can('manage_section_arms'), 403);
        $sections->assertClassArm($request->user(), $arm->id);
    }

    private function assertNotCurrent($startDate, $endDate, string $type): void
    {
        $today = today('Africa/Lagos');
        if ($today->betweenIncluded($startDate, $endDate)) {
            throw ValidationException::withMessages([$type => 'The current '.$type.' cannot be deleted. Create or select a later '.$type.' first.']);
        }
    }
}
