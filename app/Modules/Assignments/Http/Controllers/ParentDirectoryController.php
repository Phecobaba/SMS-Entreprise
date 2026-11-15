<?php

namespace App\Modules\Assignments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Services\ParentDirectory;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\TemporaryCredentials;
use App\Modules\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParentDirectoryController extends Controller
{
    public function index(Request $request, ParentDirectory $directory): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($data['search'] ?? ''));

        return view('parents.index', ['parents' => $directory->index($request->user(), $search), 'search' => $search]);
    }

    public function show(ParentProfile $parent, Request $request, ParentDirectory $directory): View
    {
        $data = $request->validate(['child_search' => ['nullable', 'string', 'max:100']]);
        $search = trim((string) ($data['child_search'] ?? ''));
        $parent = $directory->find($request->user(), $parent->id);

        return view('parents.show', [
            'parent' => $parent,
            'childSearch' => $search,
            'studentChoices' => $request->user()->can('manage_parent_accounts')
                ? $directory->studentChoices($request->user(), $search)->reject(fn (Student $student) => $parent->students->contains('id', $student->id))
                : collect(),
        ]);
    }

    public function reset(ParentProfile $parent, Request $request, ParentDirectory $directory, TemporaryCredentials $credentials): Response
    {
        $parent = $directory->find($request->user(), $parent->id);
        $reset = $credentials->reset($parent->user, $request->user(), 'parent_password_reset');

        return response()->view('students.reset-credentials', $reset + [
            'heading' => $parent->user->name,
            'student' => $parent->students->first(),
            'parent' => $parent,
            'doneUrl' => route('parents.show', $parent),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')->header('Pragma', 'no-cache');
    }

    public function updateEmail(ParentProfile $parent, Request $request, ParentDirectory $directory): RedirectResponse
    {
        $parent = $directory->find($request->user(), $parent->id);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')
                ->where('school_id', app(TenantContext::class)->id())->ignore($parent->user_id)],
        ]);
        DB::transaction(function () use ($parent, $request, $data): void {
            $before = $parent->user->email;
            $parent->user->update(['email' => strtolower(trim($data['email'])), 'email_verified_at' => now()]);
            app(AuditRecorder::class)->record($request->user(), 'parent_email_changed', $parent->user, [
                'had_email' => filled($before), 'email_confirmed' => true,
            ], 'Accounts');
        });

        return redirect()->route('parents.show', $parent)->with('status', 'Parent email saved and confirmed.');
    }

    public function link(ParentProfile $parent, Request $request, ParentDirectory $directory): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $directory->link($parent, Student::findOrFail((int) $data['student_id']), $request->user());

        return redirect()->route('parents.show', $parent)->with('status', 'Child linked.');
    }

    public function unlink(ParentProfile $parent, Student $student, Request $request, ParentDirectory $directory): RedirectResponse
    {
        abort_unless($parent->students()->whereKey($student->id)->exists(), 404);
        $directory->unlink($parent, $student, $request->user());

        return redirect()->route('parents.show', $parent)->with('status', 'Child unlinked.');
    }
}
