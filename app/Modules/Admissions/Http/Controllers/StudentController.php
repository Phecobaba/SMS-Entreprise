<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\RegistrationNumbers;
use App\Modules\Admissions\Services\StudentAccounts;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Services\ParentDirectory;
use App\Modules\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view_students');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([Student::STATUS_ACTIVE, Student::STATUS_AWAITING, Student::STATUS_GRADUATED])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $students = Student::with(['user', 'classArm.schoolClass.level.section'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($part) use ($search): void {
                    $part->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%'))
                        ->orWhere('registration_number', 'like', '%'.strtoupper($search).'%')
                        ->orWhereHas('registrationHistory', fn ($history) => $history->where('new_number', 'like', '%'.strtoupper($search).'%'));
                });
            })
            ->when($filters['section'] ?? null, fn ($query, $id) => $query->whereHas(
                'classArm.schoolClass.level', fn ($level) => $level->where('section_id', $id),
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('id')->paginate(20)->withQueryString();

        return view('students.index', [
            'students' => $students,
            'sections' => Section::orderBy('name')->limit(100)->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, RegistrationNumbers $numbers): View
    {
        Gate::authorize('create_students');
        $arms = ClassArm::with('schoolClass.level.section')->whereHas('schoolClass.level.section')
            ->orderBy('id')->limit(500)->get();
        $selectedArm = $request->integer('class_arm_id');
        $parentSearch = trim((string) $request->query('parent_search', ''));
        $parents = $parentSearch === '' ? collect() : ParentProfile::with('user')
            ->whereHas('user', fn ($query) => $query->where('name', 'like', '%'.$parentSearch.'%')
                ->orWhere('username', 'like', '%'.$parentSearch.'%')->orWhere('email', 'like', '%'.$parentSearch.'%'))
            ->limit(20)->get();

        $selectedArmModel = $selectedArm ? $arms->firstWhere('id', $selectedArm) : null;

        return view('students.create', compact('arms', 'selectedArm', 'selectedArmModel', 'parentSearch', 'parents') + [
            'preview' => $selectedArm && $arms->contains('id', $selectedArm) ? $numbers->preview($selectedArm) : null,
        ]);
    }

    public function store(Request $request, StudentAccounts $accounts): Response
    {
        Gate::authorize('create_students');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'class_arm_id' => ['required', 'integer'],
            'registration_mode' => ['required', Rule::in(['automatic', 'typed'])],
            'registration_number' => ['nullable', 'required_if:registration_mode,typed', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('school_id', app(TenantContext::class)->id())],
            'parent_mode' => ['required', Rule::in(['none', 'new', 'existing'])],
            'parent_name' => ['nullable', 'required_if:parent_mode,new', 'string', 'max:255'],
            'parent_username' => ['nullable', 'required_if:parent_mode,new', 'string', 'max:30'],
            'parent_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('school_id', app(TenantContext::class)->id())],
            'parent_id' => ['nullable', 'required_if:parent_mode,existing', 'integer'],
        ]);
        $created = $accounts->create($data, $request->user());
        $created['student']->loadMissing('classArm.schoolClass');

        return response()->view('students.credentials', $created)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function show(Student $student, Request $request, RegistrationNumbers $numbers): View
    {
        abort_unless($request->user()->can('manage_student_accounts') || $request->user()->can('change_registration_numbers') || $request->user()->can('view_parents'), 403);
        if ($request->user()->can('view_parents') && ! $request->user()->can('manage_student_accounts') && ! $request->user()->can('change_registration_numbers')) {
            app(ParentDirectory::class)->assertStudentVisible($request->user(), $student);
        } elseif (! $request->user()->can('manage_student_accounts')) {
            $numbers->authorizeChange($student, $request->user());
        }
        $student->load(['user', 'classArm.schoolClass.level.section', 'registrationHistory.changedBy', 'parents.user', 'parents.students.user']);

        return view('students.show', compact('student'));
    }

    public function update(Student $student, Request $request, StudentAccounts $accounts): RedirectResponse
    {
        Gate::authorize('manage_student_accounts');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $accounts->update($student, $data, $request->user());

        return redirect()->route('students.show', $student)->with('status', 'Student details saved.');
    }

    public function changeNumber(Student $student, Request $request, RegistrationNumbers $numbers): RedirectResponse
    {
        $data = $request->validate(['registration_number' => ['required', 'string', 'max:64']]);
        $numbers->change($student, $data['registration_number'], $request->user());

        return redirect()->route('students.show', $student)->with('status', 'Registration number changed.');
    }

    public function resetStudent(Student $student, Request $request, StudentAccounts $accounts): Response
    {
        $reset = $accounts->resetStudent($student->load('user', 'classArm.schoolClass'), $request->user());

        return response()->view('students.reset-credentials', $reset + ['heading' => $student->user->name, 'student' => $student])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')->header('Pragma', 'no-cache');
    }

    public function resetParent(Student $student, ParentProfile $parent, Request $request, StudentAccounts $accounts): Response
    {
        abort_unless($parent->students()->whereKey($student->id)->exists(), 404);
        $reset = $accounts->resetParent($parent->load('user', 'students.user'), $request->user());

        return response()->view('students.reset-credentials', $reset + ['heading' => $parent->user->name, 'student' => $student, 'parent' => $parent])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')->header('Pragma', 'no-cache');
    }
}
