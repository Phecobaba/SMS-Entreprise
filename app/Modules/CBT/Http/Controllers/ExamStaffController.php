<?php

namespace App\Modules\CBT\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Models\ExamType;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Results\Services\ResultAssessmentCatalog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamStaffController extends Controller
{
    public function index(ResultAcademicCatalog $academics, ResultAssessmentCatalog $components): View
    {
        return view('cbt.staff-index', [
            'types' => ExamType::orderBy('name')->get(),
            'exams' => Exam::orderByDesc('id')->paginate(20),
            'components' => $components->options(),
            ...$academics->options(),
        ]);
    }

    public function type(Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'in:Internal,Entrance,Mock,Practice,Assessment,Promotional']]);
        $workflow->createType($data['name'], $request->user());

        return back()->with('status', 'Exam type ready.');
    }

    public function exam(Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'exam_type_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:160'],
            'duration' => ['required', 'integer', 'min:1', 'max:240'], 'scheduled_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:scheduled_at'],
            'class_id' => ['nullable', 'integer'], 'subject_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'], 'assessment_component_id' => ['nullable', 'integer'],
            'pass_mark' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'result_release_type' => ['required', 'in:Immediate,Scheduled,Manual'], 'result_release_at' => ['nullable', 'date'],
            'show_score' => ['sometimes', 'boolean'], 'show_correct_answers' => ['sometimes', 'boolean'],
            'show_explanations' => ['sometimes', 'boolean'], 'requires_approval' => ['sometimes', 'boolean'],
        ]);
        foreach (['scheduled_at', 'closes_at', 'result_release_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse($data[$field], 'Africa/Lagos')->utc()->toDateTimeString();
            }
        }
        $exam = $workflow->createExam($data, $request->user());

        return redirect()->route('cbt.staff.show', $exam)->with('status', 'Draft exam created. Add questions, then publish.');
    }

    public function show(int $exam): View
    {
        return view('cbt.staff-show', ['exam' => Exam::with('questions.options')->findOrFail($exam)]);
    }

    public function question(int $exam, Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'], 'marks' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'options' => ['required', 'array', 'min:2', 'max:6'], 'options.*' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0', 'max:5'],
        ]);
        $workflow->addQuestion(Exam::findOrFail($exam), $data['text'], (float) $data['marks'], $data['explanation'] ?? null, $data['options'], (int) $data['correct_index'], $request->user());

        return back()->with('status', 'Question added.');
    }

    public function publish(int $exam, Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->publish(Exam::findOrFail($exam), $request->user());

        return back()->with('status', 'Exam published.');
    }

    public function approve(int $exam, Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->approveRelease(Exam::findOrFail($exam), $request->user());

        return back()->with('status', 'Result release approved.');
    }

    public function release(int $exam, Request $request, ExamWorkflow $workflow): RedirectResponse
    {
        $workflow->release(Exam::findOrFail($exam), $request->user());

        return back()->with('status', 'Manual release activated.');
    }
}
