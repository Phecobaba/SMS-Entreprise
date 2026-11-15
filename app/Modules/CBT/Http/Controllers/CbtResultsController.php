<?php

namespace App\Modules\CBT\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\CBT\Services\CbtAccessScope;
use App\Modules\CBT\Services\CbtAnswerReview;
use App\Modules\CBT\Services\ExamAttemptLifecycle;
use App\Modules\CBT\Services\ExamClosing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CbtResultsController extends Controller
{
    public function index(Request $request, CbtAccessScope $scope): View
    {
        $exams = $scope->resultsQuery($request->user())
            ->with(['schoolClass.level.section', 'subject', 'type'])
            ->withCount(['submissions' => fn ($query) => $query->where('status', 'Submitted')
                ->select(DB::raw('count(distinct student_id) + count(distinct candidate_id)'))])
            ->orderByDesc('id')->paginate(20);
        app(ExamAttemptLifecycle::class)->submitExpiredForExams($exams->getCollection());
        $exams->getCollection()->each(fn ($exam) => $exam->setAttribute('effective_status', app(ExamClosing::class)->displayStatus($exam)));

        return view('cbt.results-index', compact('exams'));
    }

    public function show(int $exam, Request $request, CbtAccessScope $scope): View
    {
        $record = $scope->resultExam($request->user(), $exam)
            ->load(['schoolClass.level.section', 'subject', 'type']);
        app(ExamAttemptLifecycle::class)->submitExpiredForExam($record, app(ExamClosing::class)->countsAsClosed($record));
        $record->setAttribute('effective_status', app(ExamClosing::class)->displayStatus($record));
        $attempts = $record->submissions()->where('status', 'Submitted')
            ->where(function ($query): void {
                $query->whereNull('student_id')->orWhereNotExists(function ($newer): void {
                    $newer->selectRaw('1')->from('exam_submissions as newer')
                        ->whereColumn('newer.school_id', 'exam_submissions.school_id')
                        ->whereColumn('newer.exam_id', 'exam_submissions.exam_id')
                        ->whereColumn('newer.student_id', 'exam_submissions.student_id')
                        ->where('newer.status', 'Submitted')
                        ->whereColumn('newer.attempt_number', '>', 'exam_submissions.attempt_number');
                });
            })
            ->with(['student.user', 'candidate'])->latest('submitted_at')->paginate(20);
        $studentIds = $attempts->getCollection()->pluck('student_id')->filter();
        $attemptHistories = ExamSubmission::where('exam_id', $record->id)->whereIn('student_id', $studentIds)
            ->orderBy('attempt_number')->get()->groupBy('student_id');

        return view('cbt.results-show', compact('record', 'attempts', 'attemptHistories'));
    }

    public function pupil(int $exam, int $submission, Request $request, CbtAccessScope $scope): View
    {
        $record = $scope->resultExam($request->user(), $exam)
            ->load(['schoolClass.level.section', 'subject', 'type', 'questions.options']);
        app(ExamAttemptLifecycle::class)->submitExpiredForExam($record, app(ExamClosing::class)->countsAsClosed($record));
        $record->setAttribute('effective_status', app(ExamClosing::class)->displayStatus($record));
        $attempt = ExamSubmission::where('exam_id', $record->id)->where('status', 'Submitted')
            ->with(['student.user', 'candidate', 'answers.selections'])->findOrFail($submission);
        $answers = $attempt->answers->keyBy('question_id');
        $reviews = $record->questions->mapWithKeys(fn ($question): array => [
            $question->id => app(CbtAnswerReview::class)->forQuestion($question, $answers->get($question->id)),
        ]);
        $historyQuery = ExamSubmission::where('exam_id', $record->id);
        if ($attempt->student_id) {
            $historyQuery->where('student_id', $attempt->student_id);
        } else {
            $historyQuery->where('candidate_id', $attempt->candidate_id);
        }
        $attemptHistory = $historyQuery->orderBy('attempt_number')->get();

        return view('cbt.results-pupil', compact('record', 'attempt', 'answers', 'reviews', 'attemptHistory'));
    }
}
