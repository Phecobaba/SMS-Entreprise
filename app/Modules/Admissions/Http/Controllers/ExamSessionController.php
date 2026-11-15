<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ExamSession;
use App\Modules\Admissions\Services\CandidateAccount;
use App\Modules\Admissions\Services\ExamSessionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamSessionController extends Controller
{
    public function index(Request $request, ExamSessionWorkflow $workflow): View
    {
        $sessions = $workflow->scopeSessions(
            ExamSession::with('section')->withCount('applications'),
            $request->user(),
        )->orderByDesc('starts_at')->paginate(20);

        $sections = $workflow->sections($request->user());

        return view('applications.sessions', [
            'sessions' => $sessions,
            'sections' => $sections,
            'editing' => null,
            'canChooseSection' => $request->user()->can('manage_admissions') || $sections->count() > 1,
            'allowSchoolWide' => $request->user()->can('manage_admissions'),
            'accountPreview' => null,
            'accountCandidates' => collect(),
        ]);
    }

    public function edit(ExamSession $session, Request $request, ExamSessionWorkflow $workflow): View
    {
        $workflow->assertSession($request->user(), $session);
        $sessions = $workflow->scopeSessions(
            ExamSession::with('section')->withCount('applications'),
            $request->user(),
        )->orderByDesc('starts_at')->paginate(20);

        $sections = $workflow->sections($request->user());

        return view('applications.sessions', [
            'sessions' => $sessions,
            'sections' => $sections,
            'editing' => $session,
            'canChooseSection' => $request->user()->can('manage_admissions') || $sections->count() > 1,
            'allowSchoolWide' => $request->user()->can('manage_admissions'),
            'accountPreview' => $request->user()->can('manage_admissions')
                ? app(CandidateAccount::class)->preview($session, $request->user()) : null,
            'accountCandidates' => $request->user()->can('manage_admissions')
                ? $session->applications()->whereHas('candidate')->with('candidate.user')->orderBy('child_surname')->get() : collect(),
        ]);
    }

    public function store(Request $request, ExamSessionWorkflow $workflow): RedirectResponse
    {
        $session = $workflow->create($this->validated($request), $request->user());

        return redirect()->route('admissions.sessions.edit', $session)->with('status', 'Exam session created.');
    }

    public function update(ExamSession $session, Request $request, ExamSessionWorkflow $workflow): RedirectResponse
    {
        $data = $this->validated($request);
        $workflow->update($session, $data, $request->user(), $request->boolean('email_applicants', true));

        return redirect()->route('admissions.sessions.edit', $session)->with('status', 'Exam session saved.');
    }

    public function destroy(ExamSession $session, Request $request, ExamSessionWorkflow $workflow): RedirectResponse
    {
        $workflow->delete($session, $request->user());

        return redirect()->route('admissions.sessions.index')->with('status', 'Exam session deleted.');
    }

    public function assign(Request $request, ExamSessionWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'applications' => ['required', 'array', 'min:1'],
            'applications.*' => ['integer'],
            'exam_session_id' => ['required', 'integer', Rule::exists('exam_sessions', 'id')],
        ]);
        $session = ExamSession::findOrFail($data['exam_session_id']);
        $report = $workflow->assign($data['applications'], $session, $request->user());

        return redirect()->route('admissions.applications.index', ['tab' => 'awaiting_date'])
            ->with('status', "{$report['assigned']} application(s) assigned. {$report['skipped']} skipped.");
    }

    public function move(Application $application, Request $request, ExamSessionWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['exam_session_id' => ['required', 'integer', Rule::exists('exam_sessions', 'id')]]);
        $session = ExamSession::findOrFail($data['exam_session_id']);
        $changed = $workflow->move($application, $session, $request->user());

        return back()->with('status', $changed ? 'Applicant moved to the exam session.' : 'Applicant is already in that session.');
    }

    public function remove(Application $application, Request $request, ExamSessionWorkflow $workflow): RedirectResponse
    {
        $workflow->remove($application, $request->user());

        return back()->with('status', 'Applicant removed from the exam session.');
    }

    public function createAccounts(ExamSession $session, Request $request, CandidateAccount $accounts): Response
    {
        $data = $request->validate(['applications' => ['required', 'array', 'min:1'], 'applications.*' => ['integer']]);
        $result = $accounts->createForSession($session, $data['applications'], $request->user());

        return $this->pdf($result['pdf'], 'candidate-credentials-'.$session->id.'.pdf');
    }

    public function resetAccounts(ExamSession $session, Request $request, CandidateAccount $accounts): Response
    {
        $data = $request->validate(['candidates' => ['required', 'array', 'min:1'], 'candidates.*' => ['integer']]);
        $result = $accounts->resetForSession($session, $data['candidates'], $request->user());

        return $this->pdf($result['pdf'], 'candidate-replacement-credentials-'.$session->id.'.pdf');
    }

    private function pdf(string $bytes, string $name): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'venue_note' => ['required', 'string', 'max:500'],
            'is_in_person' => ['required', 'boolean'],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
        ]);
    }
}
