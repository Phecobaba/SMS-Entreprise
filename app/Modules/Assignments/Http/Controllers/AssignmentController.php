<?php

namespace App\Modules\Assignments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\AssignmentService;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Core\TenantContext;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request, AssignmentService $service, ClassArmDirectory $arms, ResultAcademicCatalog $catalog, SchoolUserDirectory $users, StudentDirectory $students): View
    {
        return view('assignments.index', [
            'assignments' => $service->staffList($request->user()),
            'arms' => $arms->all(), 'subjects' => $catalog->options()['subjects'],
            'users' => Gate::allows('manage_assignments') ? $users->activeOptions() : collect(),
            'students' => Gate::allows('manage_assignments') ? $students->roster(null, $request->user()) : null,
        ]);
    }

    public function store(Request $request, AssignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'class_arm_id' => ['required', 'integer'], 'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
        ]);
        $assignment = $service->create($data, $request->user());

        return redirect()->route('assignments.show', $assignment->id)->with('status', 'Assignment created for the full class.');
    }

    public function show(int $assignment, Request $request, AssignmentService $service): View
    {
        return view('assignments.show', $service->submissions($assignment, $request->user()));
    }

    public function mark(int $assignment, int $student, Request $request, AssignmentService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:Submitted,Not Submitted,Late']]);
        $service->mark($assignment, $student, $data['status'], $request->user());

        return back()->with('status', 'Tick saved.');
    }

    public function linkWard(Request $request, AssignmentService $service): RedirectResponse
    {
        Gate::authorize('manage_assignments');
        $data = $request->validate(['parent_user_id' => ['required', 'integer'], 'student_id' => ['required', 'integer']]);
        $service->linkWard((int) $data['parent_user_id'], (int) $data['student_id']);

        return back()->with('status', 'Parent linked to student.');
    }

    public function createParent(Request $request, AssignmentService $service): RedirectResponse
    {
        Gate::authorize('manage_assignments');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->where('school_id', app(TenantContext::class)->id())],
            'password' => ['required', 'string', 'min:12'],
            'student_id' => ['required', 'integer'],
        ]);
        $service->createParentWithWard($data);

        return back()->with('status', 'Parent account created and linked to the student.');
    }

    public function mine(Request $request, AssignmentService $service): View
    {
        $show = $request->validate(['show' => ['nullable', 'in:todo,done,all']])['show'] ?? 'todo';

        return view('assignments.mine', ['rows' => $service->forStudent($request->user(), $show),
            'heading' => 'Assignments', 'show' => $show, 'context' => app(PortalContext::class)->for($request)]);
    }

    public function parent(Request $request, AssignmentService $service): View
    {
        $show = $request->validate(['show' => ['nullable', 'in:todo,done,all']])['show'] ?? 'todo';
        $context = app(PortalContext::class)->for($request);
        abort_unless($context && $context['kind'] === 'parent', 403);

        return view('assignments.mine', ['rows' => $context['student_id']
            ? $service->forParent($request->user(), $context['student_id'], $show) : collect(),
            'heading' => 'Assignments', 'show' => $show, 'context' => $context]);
    }
}
