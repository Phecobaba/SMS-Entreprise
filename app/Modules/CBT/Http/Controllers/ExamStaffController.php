<?php

namespace App\Modules\CBT\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\CBT\Models\ExamResitGrant;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Models\Question;
use App\Modules\CBT\Services\CbtAccessScope;
use App\Modules\CBT\Services\CbtQuestionCsv;
use App\Modules\CBT\Services\CbtResultConversion;
use App\Modules\CBT\Services\ExamAttemptLifecycle;
use App\Modules\CBT\Services\ExamClosing;
use App\Modules\CBT\Services\ExamLiveProgress;
use App\Modules\CBT\Services\ExamResitGrants;
use App\Modules\CBT\Services\ExamTypeManager;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Results\Models\AssessmentComponent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExamStaffController extends Controller
{
    public function index(Request $request, CbtAccessScope $scope, ExamAttemptLifecycle $lifecycle): View
    {
        $exams = $scope->managementQuery($request->user())
            ->when($request->routeIs('cbt.staff.entrance'), fn ($query) => $query->whereNotNull('pass_mark'))
            ->with(['type', 'schoolClass.level.section', 'subject', 'levels.section'])->withCount('submissions')
            ->orderByDesc('id')->paginate(20);
        $lifecycle->submitExpiredForExams($exams->getCollection());
        $exams->getCollection()->each(fn ($exam) => $exam->setAttribute('effective_status', app(ExamClosing::class)->displayStatus($exam)));

        return view('cbt.staff-index', [
            ...$this->formOptions($request, $scope),
            'exams' => $exams,
        ]);
    }

    public function types(Request $request): View
    {
        abort_unless($request->user()->can('view_all_sections'), 403);

        return view('cbt.types', ['types' => ExamType::withCount('exams')->orderBy('name')->limit(100)->get()]);
    }

    public function type(Request $request, ExamTypeManager $manager): RedirectResponse
    {
        $manager->save(null, $this->typeData($request), $request->user());

        return redirect()->route('cbt.staff.types.index')->with('status', 'Exam type created.');
    }

    public function updateType(int $type, Request $request, ExamTypeManager $manager): RedirectResponse
    {
        $manager->save(ExamType::findOrFail($type), $this->typeData($request), $request->user());

        return redirect()->route('cbt.staff.types.index')->with('status', 'Exam type saved.');
    }

    public function destroyType(int $type, Request $request, ExamTypeManager $manager): RedirectResponse
    {
        $manager->delete(ExamType::findOrFail($type), $request->user());

        return redirect()->route('cbt.staff.types.index')->with('status', 'Exam type deleted.');
    }

    public function exam(Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $exam = $workflow->createExam($this->examData($request), $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Draft exam created. Add questions, then publish.');
    }

    public function show(int $exam, Request $request, CbtAccessScope $scope): View
    {
        return $this->examView($exam, $request, $scope, false);
    }

    public function edit(int $exam, Request $request, CbtAccessScope $scope): View
    {
        return $this->examView($exam, $request, $scope, true);
    }

    public function update(int $exam, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->updateExam($scope->managedExam($request->user(), $exam), $this->examData($request), $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Exam saved.');
    }

    public function question(int $exam, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $data = $this->questionData($request);
        $workflow->addQuestion($scope->managedExam($request->user(), $exam), $data['text'], (float) ($data['marks'] ?? 1), $data['explanation'] ?? null, $data['options'], $data['correct_options'], $request->user(), $data['option_marks'] ?? null);

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Question added.');
    }

    public function questionTemplate(int $exam, Request $request, CbtQuestionCsv $csv): Response
    {
        $download = $csv->template($request->user(), $exam);

        return response($download['contents'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$download['filename'].'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function questionPreview(int $exam, Request $request, CbtAccessScope $scope, CbtQuestionCsv $csv): View
    {
        $request->user()->loadMissing('roles.permissions');
        $data = $request->validate(['file' => ['required', 'file', 'max:1024', 'extensions:csv']]);
        $preview = $csv->preview($request->user(), $exam, $data['file']);

        return $this->examView($exam, $request, $scope, false, ['questionUploadPreview' => $preview]);
    }

    public function questionApply(int $exam, Request $request, CbtQuestionCsv $csv): RedirectResponse
    {
        $data = $request->validate(['upload_token' => ['required', 'string', 'size:48']]);
        $count = $csv->apply($request->user(), $exam, $data['upload_token']);

        return redirect()->route('cbt.staff.show', $exam)
            ->with('status', $count.' question'.($count === 1 ? '' : 's').' added.');
    }

    public function questionCancel(int $exam, Request $request, CbtQuestionCsv $csv): RedirectResponse
    {
        $data = $request->validate(['upload_token' => ['required', 'string', 'size:48']]);
        $csv->cancel($request->user(), $exam, $data['upload_token']);

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Question upload cancelled.');
    }

    public function updateQuestion(int $exam, int $question, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $record = $scope->managedExam($request->user(), $exam);
        $item = Question::where('exam_id', $record->id)->findOrFail($question);
        $data = $this->questionData($request);
        $workflow->updateQuestion($record, $item, $data['text'], (float) ($data['marks'] ?? 1), $data['explanation'] ?? null, $data['options'], $data['correct_options'], $request->user(), $data['option_marks'] ?? null);

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Question saved.');
    }

    public function destroyQuestion(int $exam, int $question, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $record = $scope->managedExam($request->user(), $exam);
        $workflow->deleteQuestion($record, Question::where('exam_id', $record->id)->findOrFail($question), $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Question deleted.');
    }

    public function publish(int $exam, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->publish($scope->managedExam($request->user(), $exam), $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Exam published.');
    }

    public function release(int $exam, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->release($scope->managedExam($request->user(), $exam), $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Results released.');
    }

    public function close(int $exam, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $submitted = $workflow->close($scope->managedExam($request->user(), $exam), $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', "Exam ended. {$submitted} active attempt".($submitted === 1 ? '' : 's').' submitted.');
    }

    public function conversion(int $exam, Request $request, CbtAccessScope $scope, CbtResultConversion $conversion): View
    {
        $record = $scope->managedExam($request->user(), $exam)->load(['type', 'schoolClass', 'subject']);

        return view('cbt.staff-conversion', ['exam' => $record, 'summary' => $conversion->preview($record, $request->user()), 'applied' => false]);
    }

    public function convert(int $exam, Request $request, CbtAccessScope $scope, CbtResultConversion $conversion): View
    {
        $record = $scope->managedExam($request->user(), $exam)->load(['type', 'schoolClass', 'subject']);

        return view('cbt.staff-conversion', ['exam' => $record, 'summary' => $conversion->apply($record, $request->user()), 'applied' => true]);
    }

    public function destroy(int $exam, Request $request, CbtAccessScope $scope, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->delete($scope->managedExam($request->user(), $exam), $request->user());

        return redirect()->route('cbt.staff.index')->with('status', 'Draft exam deleted.');
    }

    public function attempts(int $exam, Request $request, CbtAccessScope $scope): View
    {
        $record = $scope->managedExam($request->user(), $exam)->load(['schoolClass', 'subject']);
        app(ExamAttemptLifecycle::class)->submitExpiredForExam($record, app(ExamClosing::class)->countsAsClosed($record));
        $attempts = $record->submissions()->with(['student.user', 'candidate'])
            ->latest('started_at')->paginate(20);

        return view('cbt.staff-attempts', compact('record', 'attempts'));
    }

    public function progress(int $exam, Request $request, CbtAccessScope $scope, ExamAttemptLifecycle $lifecycle, ExamLiveProgress $progress): View
    {
        $record = $scope->managedExam($request->user(), $exam)->load(['schoolClass.level.section', 'subject']);
        $closed = app(ExamClosing::class)->countsAsClosed($record);
        $lifecycle->submitExpiredForExam($record, $closed);
        abort_unless($record->class_id, 404);

        $data = $progress->data($record, $request->string('status')->toString(), $closed);

        return view('cbt.staff-progress', [
            'record' => $record,
            ...$data,
        ]);
    }

    public function progressRefresh(int $exam, Request $request, CbtAccessScope $scope, ExamAttemptLifecycle $lifecycle, ExamLiveProgress $progress): JsonResponse
    {
        $record = $scope->managedExam($request->user(), $exam)->load(['schoolClass.level.section', 'subject']);
        abort_unless($record->class_id, 404);
        $closed = app(ExamClosing::class)->countsAsClosed($record);
        $lifecycle->submitExpiredForExam($record, $closed);
        $data = $progress->data($record, $request->string('status')->toString(), $closed);

        return response()->json([
            'html' => view('cbt._progress-content', ['record' => $record, ...$data])->render(),
            'updated_at' => now('Africa/Lagos')->format('H:i:s'),
        ]);
    }

    public function grantResit(int $exam, int $student, Request $request, CbtAccessScope $scope, ExamResitGrants $grants): RedirectResponse
    {
        $record = $scope->managedExam($request->user(), $exam);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'opens_until' => ['required', 'date'],
        ]);
        $ends = Carbon::parse($data['opens_until'], 'Africa/Lagos')->utc();
        $grants->grant($record, $student, $data['reason'], $ends, $request->user());

        return redirect()->route('cbt.staff.progress', $record)->with('status', 'Another attempt was granted.');
    }

    public function withdrawResit(int $exam, int $grant, Request $request, CbtAccessScope $scope, ExamResitGrants $grants): RedirectResponse
    {
        $record = $scope->managedExam($request->user(), $exam);
        $grants->withdraw($record, ExamResitGrant::findOrFail($grant), $request->user());

        return redirect()->route('cbt.staff.progress', $record)->with('status', 'The unused attempt grant was withdrawn.');
    }

    private function examView(int $exam, Request $request, CbtAccessScope $scope, bool $editing, array $extra = []): View
    {
        $record = $scope->managedExam($request->user(), $exam)
            ->load(['questions.options', 'type', 'schoolClass.level.section', 'subject', 'levels.section'])
            ->loadCount('submissions');
        app(ExamAttemptLifecycle::class)->submitExpiredForExam($record, app(ExamClosing::class)->countsAsClosed($record));
        $record->setAttribute('effective_status', app(ExamClosing::class)->displayStatus($record));

        return view('cbt.staff-show', [
            'exam' => $record,
            'editing' => $editing,
            ...$this->formOptions($request, $scope, $record),
            ...$extra,
        ]);
    }

    private function examData(Request $request): array
    {
        $data = $request->validate([
            'exam_type_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:160'],
            'duration' => ['required', 'integer', 'min:1', 'max:240'],
            'default_question_marks' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'scheduled_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:scheduled_at'],
            'closes_automatically' => ['sometimes', 'boolean'],
            'class_id' => ['nullable', 'integer'], 'subject_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'], 'assessment_component_id' => ['nullable', 'integer'],
            'pass_mark' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'result_release_type' => ['required', 'in:Immediate,Scheduled,Manual'], 'result_release_at' => ['nullable', 'date'],
            'show_score' => ['sometimes', 'boolean'], 'show_correct_answers' => ['sometimes', 'boolean'],
            'show_explanations' => ['sometimes', 'boolean'],
            'level_ids' => ['sometimes', 'array'], 'level_ids.*' => ['integer', 'distinct'],
        ]);
        foreach (['scheduled_at', 'closes_at', 'result_release_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field], 'Africa/Lagos')->utc()->toDateTimeString();
            }
        }

        return $data;
    }

    private function questionData(Request $request): array
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'], 'marks' => ['sometimes', 'numeric', 'min:1', 'max:65535'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'options' => ['required', 'array', 'min:2', 'max:6'], 'options.*' => ['required', 'string', 'max:300'],
            'correct_index' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'correct_options' => ['sometimes', 'array', 'min:1'],
            'correct_options.*' => ['integer', 'min:0', 'max:5', 'distinct'],
            'option_marks' => ['sometimes', 'array'],
            'option_marks.*' => ['nullable', 'numeric'],
        ]);
        $data['correct_options'] = $data['correct_options'] ?? (array_key_exists('correct_index', $data) ? [(int) $data['correct_index']] : []);

        return $data;
    }

    private function formOptions(Request $request, CbtAccessScope $scope, $exam = null): array
    {
        $classes = $scope->classes($request->user())->loadMissing('classSubjects.subject');
        $sectionIds = $classes->pluck('level.section_id')->filter()->unique();
        $types = ExamType::where('active', true)->orderBy('name')->limit(100);
        if (! $scope->mayManageClassless($request->user())) {
            $types->where('name', '!=', 'Entrance');
        }

        $subjectsByClass = $classes->mapWithKeys(fn ($class): array => [
            $class->id => $class->classSubjects->map(fn ($assignment): array => [
                'id' => $assignment->subject_id,
                'name' => $assignment->subject?->name,
            ])->filter(fn (array $subject): bool => filled($subject['name']))->values()->all(),
        ])->all();
        $unassignedSubject = $exam?->class_id && $exam?->subject_id
            && ! collect($subjectsByClass[$exam->class_id] ?? [])->contains(fn (array $subject): bool => (int) $subject['id'] === (int) $exam->subject_id);
        if ($unassignedSubject && $exam->subject) {
            $subjectsByClass[$exam->class_id][] = ['id' => $exam->subject_id, 'name' => $exam->subject->name];
        }

        return [
            'types' => $types->get(),
            'classes' => $classes,
            'subjectsByClass' => $subjectsByClass,
            'unassignedExamSubject' => $unassignedSubject,
            'terms' => AcademicTerm::with('session')->orderByDesc('start_date')->get(),
            'components' => AssessmentComponent::whereIn('section_id', $sectionIds)->orderBy('name')->get(['id', 'name', 'max_score']),
            'canManageEntrance' => $scope->mayManageClassless($request->user()),
            'entranceLevels' => $scope->mayManageClassless($request->user()) && (! $exam || ! $exam->class_id)
                ? AcademicLevel::with('section')->whereNotNull('section_id')->orderBy('name')->limit(100)->get() : collect(),
        ];
    }

    private function typeData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'feeds_results' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
