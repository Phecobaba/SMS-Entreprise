<?php

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Academics\Services\TeacherFacingLabel;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Services\ResultTemplateResolver;
use App\Modules\Results\Services\ResultWorkflow;
use App\Modules\Results\Services\TeacherResultsWorkspace;
use App\Modules\Results\Services\TeacherScoreCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ResultWorkflowController extends Controller
{
    public function index(StudentDirectory $students, ResultAcademicCatalog $academics, ResultTeachingAssignments $assignments, Request $request): View
    {
        $teacherWorkspace = app(TeacherResultsWorkspace::class);
        if ($teacherWorkspace->isTeacherWorkspace($request->user())) {
            return view('results.teacher.index', $teacherWorkspace->landing($request->user(), $request));
        }

        $fullView = Gate::allows('manage_result_settings') || Gate::allows('approve_results');
        $query = Result::with(['classArm.schoolClass', 'submittedBy'])
            ->when($fullView, fn ($builder) => $builder->where('status', 'Submitted'))
            ->latest();
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
            $cardQuery->whereIn('class_arm_id', app(FormTeacherAssignments::class)->dashboardArms($request->user())->pluck('id'));
        }
        $cards = $cardQuery->paginate(20, ['*'], 'cards_page');

        $labels = $academics->labels($results->pluck('subject_id')->all(), $results->pluck('term_id')->all());
        $pairLabels = $results->mapWithKeys(fn (Result $result): array => [
            $result->id => app(TeacherFacingLabel::class)->pair(
                $labels['subjects'][$result->subject_id] ?? null,
                $result->classArm?->schoolClass?->name,
            ),
        ]);

        return view('results.index', [
            'results' => $results,
            'studentNames' => $students->names($results->pluck('student_id')->all()),
            'labels' => $labels,
            'pairLabels' => $pairLabels,
            'unassignedPairs' => $fullView ? $assignments->unassignedPairOptions($request->user()) : collect(),
            'cards' => $cards,
            'cardNames' => $students->names($cards->pluck('student_id')->all()),
        ]);
    }

    public function newForStudent(int $student, ResultWorkflow $workflow, StudentDirectory $students, ResultAcademicCatalog $academics, ResultTeachingAssignments $assignments, Request $request): View
    {
        abort(403, 'Use the assigned subject and class workspace to enter scores.');
        $armId = $students->classArmId($student);
        if (Gate::allows('manage_result_settings')) {
            app(SectionAccess::class)->assertClassArm($request->user(), $armId);
        }
        $subjects = $assignments->subjectsForArm($request->user(), $armId);
        abort_if($subjects === [], 403, 'You have no assigned subjects for this student.');
        $template = $workflow->templateForStudent($student);
        $names = $students->names([$student]);
        $placement = ClassArm::with('schoolClass')->findOrFail($armId);

        return view('results.new', [
            'studentId' => $student,
            'studentName' => $names[$student] ?? 'Student #'.$student,
            'template' => $template,
            'templateMessage' => $template ? null : $workflow->missingTemplateMessage($armId),
            ...$academics->options(),
            'subjects' => $subjects,
            'subjectLabels' => collect($subjects)->mapWithKeys(fn ($subject): array => [
                $subject->id => app(TeacherFacingLabel::class)->pair($subject->name, $placement->schoolClass?->name),
            ]),
        ]);
    }

    public function store(Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        abort_if(app(TeacherResultsWorkspace::class)->isTeacherWorkspace($request->user()), 403, 'Use your assigned subject and class to enter current-term scores.');
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

    public function importCsv(): RedirectResponse
    {
        abort(403, 'Use the assigned Teacher score import for this subject and class.');
    }

    public function teacherPair(int $class, int $subject, Request $request, TeacherResultsWorkspace $workspace): View
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);

        return view('results.teacher.pair', $workspace->pairPage($request->user(), $class, $subject, $request));
    }

    public function teacherCsvTemplate(int $class, int $subject, Request $request, TeacherResultsWorkspace $workspace, TeacherScoreCsv $csv): Response
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);
        $download = $csv->template($request->user(), $class, $subject);

        return response($download['contents'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function teacherCsvPreview(int $class, int $subject, Request $request, TeacherResultsWorkspace $workspace, TeacherScoreCsv $csv): View
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:1024', 'extensions:csv'],
        ]);
        $page = $workspace->pairPage($request->user(), $class, $subject, $request);
        $page['uploadPreview'] = $csv->preview($request->user(), $class, $subject, $data['file']);

        return view('results.teacher.pair', $page);
    }

    public function teacherCsvApply(int $class, int $subject, Request $request, TeacherResultsWorkspace $workspace, TeacherScoreCsv $csv): RedirectResponse
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);
        $data = $request->validate(['upload_token' => ['required', 'string', 'size:48']]);
        $count = $csv->apply($request->user(), $class, $subject, $data['upload_token']);

        return redirect()->route('results.teacher.pair', [$class, $subject])
            ->with('status', $count.' pupil score draft'.($count === 1 ? '' : 's').' saved.');
    }

    public function teacherEntry(int $class, int $subject, int $student, Request $request, TeacherResultsWorkspace $workspace): View
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);

        return view('results.teacher.entry', $workspace->entry($request->user(), $class, $subject, $student));
    }

    public function teacherSave(int $class, int $subject, int $student, Request $request, TeacherResultsWorkspace $workspace, ResultWorkflow $workflow): RedirectResponse
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);
        $context = $workspace->entry($request->user(), $class, $subject, $student);
        if (! $context['term']) {
            return redirect()->route('results.teacher.entry', [$class, $subject, $student])
                ->withErrors(['term' => 'Score entry is unavailable because no current term is configured.']);
        }
        $workspace->assertCurrentTerm($context['term']->id);

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $scores = array_filter($data['scores'], fn ($score) => $score !== null && $score !== '');
        $workflow->saveDraft($student, $subject, $context['term']->id, $scores, $request->user());

        return redirect()->route('results.teacher.entry', [$class, $subject, $student])->with('status', 'Draft scores saved.');
    }

    public function teacherSubmit(int $class, int $subject, int $student, Request $request, TeacherResultsWorkspace $workspace, ResultWorkflow $workflow): RedirectResponse
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);
        $context = $workspace->entry($request->user(), $class, $subject, $student);
        if (! $context['term']) {
            return redirect()->route('results.teacher.entry', [$class, $subject, $student])
                ->withErrors(['term' => 'Score entry is unavailable because no current term is configured.']);
        }
        $workspace->assertCurrentTerm($context['term']->id);

        if (! $request->has('scores')) {
            $existing = Result::where('student_id', $student)
                ->where('subject_id', $subject)
                ->where('term_id', $context['term']->id)
                ->firstOrFail();
            $workflow->submit($existing, $request->user());

            return redirect()->route('results.teacher.entry', [$class, $subject, $student])->with('status', 'Result submitted for approval.');
        }

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $scores = array_filter($data['scores'], fn ($score) => $score !== null && $score !== '');
        $workflow->saveAndSubmit($student, $subject, $context['term']->id, $scores, $request->user());

        return redirect()->route('results.teacher.entry', [$class, $subject, $student])->with('status', 'Result submitted for approval.');
    }

    public function teacherClass(int $arm, Request $request, TeacherResultsWorkspace $workspace): View
    {
        abort_unless($workspace->isTeacherWorkspace($request->user()), 403);

        return view('results.teacher.class', $workspace->formClass($request->user(), $arm));
    }

    public function show(int $result, StudentDirectory $students, ResultAcademicCatalog $academics, ResultTeachingAssignments $assignments, Request $request): View
    {
        $result = Result::with(['items.component', 'audit', 'classArm.schoolClass', 'submittedBy'])->findOrFail($result);
        if (Gate::allows('manage_result_settings') || Gate::allows('approve_results')) {
            app(SectionAccess::class)->assertClassArm($request->user(), $result->class_arm_id);
        } else {
            $placement = $academics->placement($result->class_arm_id);
            abort_unless($assignments->mayEnter($request->user(), $placement['class_id'], $result->subject_id), 403);
        }

        $labels = $academics->labels([$result->subject_id], [$result->term_id]);

        return view('results.show', [
            'result' => $result,
            'template' => app(ResultTemplateResolver::class)->stored($result->result_template_id),
            'studentName' => $students->names([$result->student_id])[$result->student_id] ?? 'Student #'.$result->student_id,
            'labels' => $labels,
            'pairLabel' => app(TeacherFacingLabel::class)->pair(
                $labels['subjects'][$result->subject_id] ?? null,
                $result->classArm?->schoolClass?->name,
            ),
            'canEnterScores' => $assignments->mayEnter($request->user(), $result->classArm->class_id, $result->subject_id),
        ]);
    }

    public function updateDraft(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['nullable', 'numeric', 'min:0']]);
        $scores = array_filter($data['scores'], fn ($score) => $score !== null && $score !== '');
        $record = Result::findOrFail($result);
        $workflow->saveDraft($record->student_id, $record->subject_id, $record->term_id, $scores, $request->user());

        return redirect()->route('results.show', $result)->with('status', 'Draft scores saved.');
    }

    public function submit(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $workflow->submit(Result::findOrFail($result), $request->user());

        return redirect()->route('results.show', $result)->with('status', 'Result submitted for approval.');
    }

    public function approve(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $workflow->approve(Result::findOrFail($result), $request->user());

        return redirect()->route('results.show', $result)->with('status', 'Result approved and locked.');
    }

    public function reject(int $result, Request $request, ResultWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:3', 'max:500']]);
        $workflow->reject(Result::findOrFail($result), $request->user(), $data['rejection_reason']);

        return redirect()->route('results.show', $result)->with('status', 'Result rejected for correction.');
    }
}
