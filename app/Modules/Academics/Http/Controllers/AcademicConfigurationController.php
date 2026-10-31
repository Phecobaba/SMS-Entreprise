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
use App\Modules\Academics\Services\SubjectManagement;
use App\Modules\Academics\Services\SubjectReferences;
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
            'sessions' => AcademicSession::with(['terms' => fn ($query) => $query->orderBy('start_date')])
                ->withCount('terms')->orderByDesc('start_date')->limit(100)->get(),
            'terms' => AcademicTerm::with('session')->orderByDesc('start_date')->limit(100)->get(),
            'sessionOptions' => AcademicSession::orderByDesc('start_date')->limit(100)->get(),
        ]);
    }

    public function index(Request $request, SectionAccess $sectionAccess): View
    {
        $this->authorizeSubjects($request);
        $actor = $request->user();
        $canViewAll = $actor->can('manage_academics');
        $sectionIds = $sectionAccess->sectionIds($actor);
        $sections = Section::when(! $canViewAll, fn ($query) => $query->whereIn('id', $sectionIds))
            ->orderBy('name')->limit(100)->get();
        $sectionId = $canViewAll
            ? ($request->filled('section') ? $request->integer('section') : null)
            : $sections->first()?->id;
        if ($sectionId) {
            $sectionAccess->assertSection($actor, $sectionId);
        }
        $classIds = $sectionId
            ? SchoolClass::whereHas('level', fn ($query) => $query->where('section_id', $sectionId))->pluck('id')
            : SchoolClass::pluck('id');
        $subjectQuery = Subject::with('section')
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId));

        return view('academics.configuration', [
            'subjects' => $subjectQuery->orderBy('name')->paginate(15, ['*'], 'subjects_page')->withQueryString(),
            'assignments' => ClassSubject::with(['schoolClass', 'subject'])->whereIn('class_id', $classIds)->latest()->paginate(15, ['*'], 'assignments_page')->withQueryString(),
            'classOptions' => SchoolClass::with('level')->whereIn('id', $classIds)->orderBy('name')->limit(100)->get(),
            'subjectOptions' => Subject::when($sectionId, fn ($query) => $query->where('section_id', $sectionId))->orderBy('name')->limit(100)->get(),
            'sections' => $sections,
            'sectionId' => $sectionId,
            'canViewAll' => $canViewAll,
            'hasSection' => $canViewAll || $sectionId !== null,
        ]);
    }

    public function storeLevel(Request $request, TenantContext $context, SectionAccess $sections): RedirectResponse
    {
        $this->authorizeLevels($request);
        if ($request->user()->can('manage_academics')) {
            $sectionData = $request->validate([
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $context->id())],
            ]);
            $sectionId = (int) $sectionData['section_id'];
        } else {
            $sectionId = (int) ($sections->sectionIds($request->user())[0] ?? 0);
            if ($request->has('section_id') && (int) $request->input('section_id') !== $sectionId) {
                throw new AuthorizationException('A head cannot create a level in another section.');
            }
        }
        $sections->assertSection($request->user(), $sectionId);
        $sectionName = Section::findOrFail($sectionId)->name;
        $submittedName = trim((string) $request->input('name'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_levels')->where('school_id', $context->id())],
        ], ['name.unique' => "A level called {$submittedName} already exists in {$sectionName}. Choose a different name."]);

        AcademicLevel::create(['section_id' => $sectionId, 'name' => trim($data['name'])]);

        return redirect()->route('academics.levels.index')->with('status', 'Academic level added.');
    }

    public function storeSession(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_sessions')->where('school_id', $context->id())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        AcademicSession::create($data);

        return redirect()->route('academics.sessions.index')->with('status', 'Academic session added.');
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

        return redirect()->route('academics.sessions.index')->with('status', 'Term added.');
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

        return redirect()->route('academics.classes.index')->with('status', 'Class added.');
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

        return redirect()->route('academics.classes.index')->with('status', 'Class updated.');
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

        return redirect()->route('academics.classes.index')->with('status', 'Class arm added.');
    }

    public function updateLevel(Request $request, AcademicLevel $level, TenantContext $context, SectionAccess $sections, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeLevels($request);
        $sections->assertLevel($request->user(), $level->id);
        if ($request->has('section_id') && (int) $request->input('section_id') !== (int) $level->section_id) {
            throw new AuthorizationException('A level cannot be moved to another section. Delete it and create it in the correct section.');
        }
        $submittedName = trim((string) $request->input('name'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_levels')->where('school_id', $context->id())->ignore($level->id)],
        ], ['name.unique' => "A level called {$submittedName} already exists in {$level->section?->name}. Choose a different name."]);
        $before = $level->only(['section_id', 'name']);
        $level->update(['name' => trim($data['name'])]);
        $audit->record($request->user(), 'academic_level_updated', $level, ['before' => $before, 'after' => $level->only(['section_id', 'name'])]);

        return redirect()->route('academics.levels.index')->with('status', 'Level updated. Existing records were not changed.');
    }

    public function destroyLevel(Request $request, AcademicLevel $level, SectionAccess $sections, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeLevels($request);
        $sections->assertLevel($request->user(), $level->id);
        $guard->assertUnused($level->name, $guard->levelUsage($level), 'level');
        $before = $level->only(['section_id', 'name']);
        $audit->record($request->user(), 'academic_level_deleted', $level, ['before' => $before]);
        $level->delete();

        return redirect()->route('academics.levels.index')->with('status', 'Level deleted. No school history was removed.');
    }

    public function updateArm(Request $request, ClassArm $arm, TenantContext $context, SectionAccess $sections, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeArms($request, $arm, $sections);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('class_arms')->where('school_id', $context->id())->where('class_id', $arm->class_id)->ignore($arm->id)]]);
        $before = $arm->only(['class_id', 'name']);
        $arm->update(['name' => trim($data['name'])]);
        $audit->record($request->user(), 'class_arm_updated', $arm, ['before' => $before, 'after' => $arm->only(['class_id', 'name'])]);

        return redirect()->route('academics.classes.index')->with('status', 'Class arm renamed. Existing records were not changed.');
    }

    public function destroyArm(Request $request, ClassArm $arm, SectionAccess $sections, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeArms($request, $arm, $sections);
        $guard->assertUnused($arm->schoolClass?->name.' '.$arm->name, $guard->armUsage($arm), 'arm');
        $before = $arm->only(['class_id', 'name']);
        $audit->record($request->user(), 'class_arm_deleted', $arm, ['before' => $before]);
        $arm->delete();

        return redirect()->route('academics.classes.index')->with('status', 'Class arm deleted. No school history was removed.');
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

        return redirect()->route('academics.sessions.index')->with('status', 'Session updated. Existing records were not changed.');
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

        return redirect()->route('academics.sessions.index')->with('status', 'Session and its unused terms deleted. Nothing else was removed.');
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

        return redirect()->route('academics.sessions.index')->with('status', 'Term updated. Existing records were not changed.');
    }

    public function destroyTerm(Request $request, AcademicTerm $term, AcademicDeleteGuard $guard, AuditRecorder $audit): RedirectResponse
    {
        $this->assertNotCurrent($term->start_date, $term->end_date, 'term');
        $guard->assertUnused($term->name, $guard->termUsage($term), 'term');
        $audit->record($request->user(), 'academic_term_deleted', $term, ['before' => $term->only(['session_id', 'name', 'start_date', 'end_date'])]);
        $term->delete();

        return redirect()->route('academics.sessions.index')->with('status', 'Term deleted. No school history was removed.');
    }

    public function storeSubject(Request $request, TenantContext $context, SectionAccess $sections, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeSubjects($request);
        if ($request->user()->can('manage_academics')) {
            $sectionData = $request->validate([
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->where('school_id', $context->id())],
            ]);
            $sectionId = (int) $sectionData['section_id'];
        } else {
            $sectionId = (int) ($sections->sectionIds($request->user())[0] ?? 0);
            if ($request->has('section_id') && (int) $request->input('section_id') !== $sectionId) {
                throw new AuthorizationException('A head cannot create a subject in another section.');
            }
        }
        $sections->assertSection($request->user(), $sectionId);
        $sectionName = Section::findOrFail($sectionId)->name;
        $submittedName = trim((string) $request->input('name'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects')->where('school_id', $context->id())->where('section_id', $sectionId)],
        ], ['name.unique' => "A subject called {$submittedName} already exists in {$sectionName}. Choose a different name."]);

        $subject = Subject::create(['section_id' => $sectionId, 'name' => trim($data['name'])]);
        $audit->record($request->user(), 'subject_created', $subject, ['after' => $subject->only(['section_id', 'name'])]);

        return redirect()->route('academics.setup', ['section' => $sectionId])->with('status', 'Subject added.');
    }

    public function updateSubject(Request $request, Subject $subject, TenantContext $context, SectionAccess $sections, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeSubjects($request);
        $sections->assertSubject($request->user(), $subject->id);
        if ($request->has('section_id') && (int) $request->input('section_id') !== (int) $subject->section_id) {
            throw new AuthorizationException('A subject cannot be moved to another section. Delete it and create it in the correct section.');
        }
        $sectionId = (int) $subject->section_id;
        $submittedName = trim((string) $request->input('name'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects')->where('school_id', $context->id())->where('section_id', $sectionId)->ignore($subject->id)],
        ], ['name.unique' => "A subject called {$submittedName} already exists in {$subject->section?->name}. Choose a different name."]);
        $before = $subject->only(['section_id', 'name']);
        $subject->update(['name' => trim($data['name'])]);
        $audit->record($request->user(), 'subject_updated', $subject, ['before' => $before, 'after' => $subject->only(['section_id', 'name'])]);

        return redirect()->route('academics.setup', ['section' => $sectionId])->with('status', 'Subject updated. Existing scores and history were not changed.');
    }

    public function destroySubject(Request $request, Subject $subject, SectionAccess $sections, SubjectManagement $management, AuditRecorder $audit): RedirectResponse
    {
        $this->authorizeSubjects($request);
        $sections->assertSubject($request->user(), $subject->id);
        $management->assertUnused($subject, 'deleted');
        $sectionId = (int) $subject->section_id;
        $before = $subject->only(['section_id', 'name']);
        $audit->record($request->user(), 'subject_deleted', $subject, ['before' => $before]);
        $subject->delete();

        return redirect()->route('academics.setup', ['section' => $sectionId])->with('status', 'Subject deleted. No school history was removed.');
    }

    public function attachSubject(Request $request, TenantContext $context, SectionAccess $sections, SubjectReferences $references): RedirectResponse
    {
        $this->authorizeSubjects($request);
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $context->id())],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('school_id', $context->id())],
        ]);

        SchoolClass::findOrFail($data['class_id']);
        Subject::findOrFail($data['subject_id']);
        $sections->assertClass($request->user(), (int) $data['class_id']);
        $sections->assertSubject($request->user(), (int) $data['subject_id']);
        $references->assertClassSubjectSection((int) $data['class_id'], (int) $data['subject_id']);
        ClassSubject::firstOrCreate($data);

        return redirect()->route('academics.setup')->with('status', 'Subject assigned to class.');
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

    private function authorizeSubjects(Request $request): void
    {
        abort_unless($request->user()->can('manage_academics') || $request->user()->can('manage_section_subjects'), 403);
    }

    private function assertNotCurrent($startDate, $endDate, string $type): void
    {
        $today = today('Africa/Lagos');
        if ($today->betweenIncluded($startDate, $endDate)) {
            throw ValidationException::withMessages([$type => 'The current '.$type.' cannot be deleted. Create or select a later '.$type.' first.']);
        }
    }
}
