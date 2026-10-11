<?php

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Services\ResultCsvImport;
use App\Modules\Results\Services\ResultWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResultWorkflowController extends Controller
{
    public function index(StudentDirectory $students, ResultAcademicCatalog $academics, ResultTeachingAssignments $assignments, Request $request): View
    {
        $query = Result::latest();
        $fullView = Gate::allows('manage_result_settings') || Gate::allows('approve_results');
        $fullCardView = $fullView || Gate::allows('add_principal_comment') || Gate::allows('add_proprietor_comment');
        if ($fullView) {
            app(SectionAccess::class)->scopeResults($query, $request->user());
        } else {
            $scopes = $assignments->resultScopesFor($request->user());
            $query->where(function ($builder) use ($scopes): void {
                foreach ($scopes as $scope) {
                    $builder->orWhere(function ($part) use ($scope): void {
                        $part->where('subject_id', $scope['subject_id'])->whereIn('class_arm_id', $scope['arm_ids']);
                    });
                }
                if ($scopes === []) {
                    $builder->whereRaw('1 = 0');
                }
            });
        }
        $results = $query->paginate(20);
        $cardQuery = ResultCard::latest();
        if ($fullCardView) {
            app(SectionAccess::class)->scopeCards($cardQuery, $request->user());
        } else {
            $cardQuery->whereIn('class_arm_id', $assignments->armIdsFor($request->user()));
        }
        $cards = $cardQuery->paginate(20, ['*'], 'cards_page');

        return view('results.index', [
            'results' => $results,
            'studentNames' => $students->names($results->pluck('student_id')->all()),
            'labels' => $academics->labels($results->pluck('subject_id')->all(), $results->pluck('term_id')->all()),
            'roster' => Gate::allows('enter_results') ? $students->roster($fullView ? null : $assignments->armIdsFor($request->user()), $fullView ? $request->user() : null) : null,
            'cards' => $cards,
            'cardNames' => $students->names($cards->pluck('student_id')->all()),
        ]);
    }

    public function newForStudent(int $student, ResultWorkflow $workflow, StudentDirectory $students, ResultAcademicCatalog $academics, ResultTeachingAssignments $assignments, Request $request): View
    {
        $armId = $students->classArmId($student);
        if (Gate::allows('manage_result_settings')) {
            app(SectionAccess::class)->assertClassArm($request->user(), $armId);
        }
        $subjects = $assignments->subjectsForArm($request->user(), $armId);
        abort_if($subjects === [], 403, 'You have no assigned subjects for this student.');
        $template = $workflow->templateForStudent($student);
        $names = $students->names([$student]);

        return view('results.new', [
            'studentId' => $student,
            'studentName' => $names[$student] ?? 'Student #'.$student,
            'template' => $template,
            ...$academics->options(),
            'subjects' => $subjects,
        ]);
    }

    public function store(Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $scores = array_filter($data['scores'], fn ($score) => $score !== null && $score !== '');
        $result = $workflow->saveDraft((int) $data['student_id'], (int) $data['subject_id'], (int) $data['term_id'], $scores, $request->user());

        return redirect()->route('results.show', $result)->with('status', 'Draft scores saved.');
    }

    public function importCsv(Request $request, ResultCsvImport $importer): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $count = $importer->import($data['file'], $request->user());

        return back()->with('status', "CSV scores saved as drafts for {$count} result(s).");
    }

    public function show(int $result, StudentDirectory $students, ResultAcademicCatalog $academics, ResultTeachingAssignments $assignments, Request $request): View
    {
        $result = Result::with(['items.component', 'audit'])->findOrFail($result);
        if (Gate::allows('manage_result_settings') || Gate::allows('approve_results')) {
            app(SectionAccess::class)->assertClassArm($request->user(), $result->class_arm_id);
        } else {
            $placement = $academics->placement($result->class_arm_id);
            abort_unless($assignments->mayEnter($request->user(), $placement['class_id'], $result->subject_id), 403);
        }

        return view('results.show', [
            'result' => $result,
            'template' => ResultTemplate::with('components.component')->findOrFail($result->result_template_id),
            'studentName' => $students->names([$result->student_id])[$result->student_id] ?? 'Student #'.$result->student_id,
            'labels' => $academics->labels([$result->subject_id], [$result->term_id]),
        ]);
    }

    public function updateDraft(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['nullable', 'numeric', 'min:0']]);
        $scores = array_filter($data['scores'], fn ($score) => $score !== null && $score !== '');
        $record = Result::findOrFail($result);
        $workflow->saveDraft($record->student_id, $record->subject_id, $record->term_id, $scores, $request->user());

        return back()->with('status', 'Draft scores saved.');
    }

    public function submit(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $workflow->submit(Result::findOrFail($result), $request->user());

        return back()->with('status', 'Result submitted for approval.');
    }

    public function approve(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $workflow->approve(Result::findOrFail($result), $request->user());

        return back()->with('status', 'Result approved and locked.');
    }

    public function reject(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $workflow->reject(Result::findOrFail($result), $request->user(), $data['rejection_reason']);

        return back()->with('status', 'Result rejected for correction.');
    }
}
