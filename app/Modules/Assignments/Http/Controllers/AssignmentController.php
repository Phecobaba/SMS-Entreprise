<?php

namespace App\Modules\Assignments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\AssignmentService;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Core\Services\UsernameRules;
use App\Modules\Core\TenantContext;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request, AssignmentService $service, ResultTeachingAssignments $teaching, SchoolUserDirectory $users, StudentDirectory $students): View
    {
        $pairs = $teaching->pairOptionsFor($request->user(), 'manage_assignments');

        return view('assignments.index', [
            'assignments' => $service->staffList($request->user()),
            'pairs' => $pairs,
            'arms' => ClassArm::with('schoolClass')->whereIn('class_id', $pairs->pluck('class_id'))->orderBy('class_id')->orderBy('name')->get(),
            'students' => Gate::allows('manage_assignments') ? $students->roster(null, $request->user()) : null,
        ]);
    }

    public function searchParents(Request $request, SchoolUserDirectory $users): JsonResponse
    {
        Gate::authorize('manage_assignments');
        $term = (string) $request->query('q', '');
        if (mb_strlen(trim($term)) < 2) {
            return response()->json(['message' => 'Enter at least 2 characters.', 'results' => []]);
        }

        return response()->json(['results' => $users->searchParents($term)]);
    }

    public function store(Request $request, AssignmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'class_arm_id' => ['required', 'integer'], 'pair' => ['required', 'regex:/^\d+:\d+$/'],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
        ]);
        [$data['class_id'], $data['subject_id']] = array_map('intval', explode(':', $data['pair'], 2));
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

        return redirect()->route('assignments.show', $assignment)->with('status', 'Tick saved.');
    }

    public function linkWard(Request $request, AssignmentService $service, SchoolUserDirectory $users): RedirectResponse
    {
        Gate::authorize('manage_assignments');
        $data = $request->validate(['parent_user_id' => ['required', 'integer'], 'student_id' => ['required', 'integer']]);
        $users->parentAccount((int) $data['parent_user_id']);
        $service->linkWard((int) $data['parent_user_id'], (int) $data['student_id']);

        return redirect()->route('assignments.index')->with('status', 'Parent linked to student.');
    }

    public function createParent(Request $request, AssignmentService $service): Response
    {
        Gate::authorize('manage_assignments');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->where('school_id', app(TenantContext::class)->id())],
            'student_id' => ['required', 'integer'],
        ]);
        app(UsernameRules::class)->validateAvailable($data['username']);
        $created = $service->createParentWithWard($data, $request->user());

        return response()->view('students.reset-credentials', $created + [
            'heading' => $created['user']->name,
            'student' => app(StudentDirectory::class)->findForPortal((int) $data['student_id']),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')->header('Pragma', 'no-cache');
    }

    public function mine(Request $request, AssignmentService $service): View
    {
        $show = $request->validate(['show' => ['nullable', 'in:todo,done,all']])['show'] ?? 'todo';
        $context = app(PortalContext::class)->for($request);
        if ($context['selected']['restricted'] ?? false) {
            return view('portals.restricted', compact('context'));
        }

        return view('assignments.mine', ['rows' => $service->forStudent($request->user(), $show),
            'heading' => 'Assignments', 'show' => $show, 'context' => $context]);
    }

    public function parent(Request $request, AssignmentService $service): View
    {
        $show = $request->validate(['show' => ['nullable', 'in:todo,done,all']])['show'] ?? 'todo';
        $context = app(PortalContext::class)->for($request);
        abort_unless($context && $context['kind'] === 'parent', 403);
        if ($context['selected']['restricted'] ?? false) {
            return view('portals.restricted', compact('context'));
        }

        return view('assignments.mine', ['rows' => $context['student_id']
            ? $service->forParent($request->user(), $context['student_id'], $show) : collect(),
            'heading' => 'Assignments', 'show' => $show, 'context' => $context]);
    }
}
