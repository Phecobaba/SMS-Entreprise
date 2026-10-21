<?php

namespace App\Modules\CBT\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Portals\Services\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExamParticipantController extends Controller
{
    public function index(Request $request, ExamWorkflow $workflow): View
    {
        $context = app(PortalContext::class)->for($request);
        if (($context['selected']['restricted'] ?? false) === true) {
            return view('portals.restricted', compact('context'));
        }

        return view('cbt.participant-index', [
            'exams' => $workflow->availableFor($request->user()),
            'notifications' => app(InAppNotifications::class)->latestUnread($request->user()),
        ]);
    }

    public function start(int $exam, Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $attempt = $workflow->start(Exam::findOrFail($exam), $request->user());

        if ($attempt->status === 'Submitted' && ! $workflow->isReleased($attempt->exam)) {
            return redirect()->route('cbt.mine')->with('status', 'Your exam is submitted. The result is not released yet.');
        }

        return redirect()->route($attempt->status === 'Submitted' ? 'cbt.result' : 'cbt.sit', $attempt);
    }

    public function sit(int $submission, Request $request, ExamWorkflow $workflow): View|RedirectResponse
    {
        $attempt = ExamSubmission::with(['exam', 'answers'])->findOrFail($submission);
        $workflow->assertOwner($attempt, $request->user());
        abort_unless($attempt->status === 'In Progress', 403);
        if ($workflow->remainingSeconds($attempt) === 0) {
            $workflow->submit($attempt, [], $request->user());

            if ($workflow->isReleased($attempt->exam)) {
                return redirect()->route('cbt.result', $attempt)->with('status', 'Time ended. Your saved answers were submitted.');
            }

            return redirect()->route('cbt.mine')->with('status', 'Time ended. Your saved answers were submitted.');
        }
        $questions = Question::with('options')->where('exam_id', $attempt->exam_id)->orderBy('position')->get();

        return view('cbt.sit', [
            'attempt' => $attempt,
            'questions' => $questions,
            'remainingSeconds' => $workflow->remainingSeconds($attempt),
        ]);
    }

    public function answer(int $submission, Request $request, ExamWorkflow $workflow): Response
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['nullable', 'integer'],
            'flagged' => ['required', 'boolean'],
        ]);
        $workflow->saveAnswer(
            ExamSubmission::findOrFail($submission),
            (int) $data['question_id'],
            isset($data['option_id']) ? (int) $data['option_id'] : null,
            (bool) $data['flagged'],
            $request->user(),
        );

        return response()->noContent();
    }

    public function submit(int $submission, Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['required', 'integer']]);
        $attempt = $workflow->submit(ExamSubmission::findOrFail($submission), $data['answers'] ?? [], $request->user());

        if (! $workflow->isReleased($attempt->exam)) {
            return redirect()->route('cbt.mine')->with('status', 'Exam submitted. The result is not released yet.');
        }

        return redirect()->route('cbt.result', $attempt)->with('status', 'Exam submitted.');
    }

    public function result(int $submission, Request $request, ExamWorkflow $workflow): View
    {
        $data = $workflow->resultFor(ExamSubmission::findOrFail($submission), $request->user());

        return view('cbt.result', $data);
    }
}
