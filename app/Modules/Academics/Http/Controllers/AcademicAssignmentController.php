<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\AcademicAssignmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicAssignmentController extends Controller
{
    public function bulkSubjects(Request $request, AcademicAssignmentManager $manager): View
    {
        $catalog = $manager->catalog($request->user(), 'subjects', $this->sectionFrom($request));

        return view('academics.bulk-subjects', [
            ...$catalog,
            'step' => in_array($request->query('step'), ['level', 'department', 'classes', 'subjects'], true)
                ? $request->query('step') : 'level',
            'selectedLevelId' => $request->integer('level_id') ?: null,
            'selectedDepartmentId' => $request->filled('department_id') ? $request->integer('department_id') : null,
            'selectedClassIds' => collect((array) $request->query('class_ids'))->map(fn ($id) => (int) $id)->all(),
            'report' => session('assignment_report'),
        ]);
    }

    public function reviewBulkSubjects(Request $request, AcademicAssignmentManager $manager): View
    {
        $data = $this->bulkData($request);
        $catalog = $manager->catalog($request->user(), 'subjects', $data['section_id']);
        $preview = $manager->previewBulkSubjects($request->user(), $data['section_id'], $data['level_id'], $data['department_id'] ?? null, $data['class_ids'], $data['subject_ids']);

        return view('academics.bulk-subjects', [
            ...$catalog,
            'step' => 'review',
            'selectedLevelId' => $data['level_id'],
            'selectedDepartmentId' => $data['department_id'] ?? null,
            'selectedClassIds' => array_map('intval', $data['class_ids']),
            'selectedSubjectIds' => array_map('intval', $data['subject_ids']),
            'preview' => $preview,
            'report' => null,
        ]);
    }

    public function applyBulkSubjects(Request $request, AcademicAssignmentManager $manager): RedirectResponse
    {
        $data = $this->bulkData($request);
        $report = $manager->applyBulkSubjects($request->user(), $data['section_id'], $data['level_id'], $data['department_id'] ?? null, $data['class_ids'], $data['subject_ids']);

        return redirect()->route('academics.bulk-subjects.index', ['section' => $data['section_id']])
            ->with('assignment_report', $report)->with('status', $report['added'] ? 'Subjects added.' : 'Nothing to add.');
    }

    public function teacherAssignments(Request $request, AcademicAssignmentManager $manager): View
    {
        $catalog = $manager->teacherCatalog($request->user(), $this->sectionFrom($request));

        return view('academics.teacher-assignments', [
            ...$catalog,
            'mode' => $request->query('mode') === 'class' ? 'class' : 'teacher',
            'selectedTeacherId' => $request->integer('teacher_id') ?: null,
            'selectedClassId' => $request->integer('class_id') ?: null,
            'report' => session('assignment_report'),
        ]);
    }

    public function reviewTeacherAssignments(Request $request, AcademicAssignmentManager $manager): View
    {
        $data = $this->teacherData($request);
        $catalog = $manager->teacherCatalog($request->user(), $data['section_id']);
        if ($data['mode'] === 'teacher') {
            $preview = $manager->previewTeacher($request->user(), $data['section_id'], $data['teacher_id'], $data['pairs'] ?? []);
        } else {
            $preview = $manager->previewClass($request->user(), $data['section_id'], $data['class_id'], $data['teachers'] ?? []);
        }

        return view('academics.teacher-assignments', [
            ...$catalog,
            'mode' => $data['mode'],
            'selectedTeacherId' => $data['teacher_id'] ?? null,
            'selectedClassId' => $data['class_id'] ?? null,
            'postedPairs' => $data['pairs'] ?? [],
            'postedTeachers' => $data['teachers'] ?? [],
            'preview' => $preview,
            'report' => null,
        ]);
    }

    public function applyTeacherAssignments(Request $request, AcademicAssignmentManager $manager): RedirectResponse
    {
        $data = $this->teacherData($request);
        $report = $data['mode'] === 'teacher'
            ? $manager->applyTeacher($request->user(), $data['section_id'], $data['teacher_id'], $data['pairs'] ?? [])
            : $manager->applyClass($request->user(), $data['section_id'], $data['class_id'], $data['teachers'] ?? []);

        return redirect()->route('academics.teacher-assignments.index', [
            'section' => $data['section_id'],
            'mode' => $data['mode'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
        ])->with('assignment_report', $report)->with('status', 'Teacher assignments saved.');
    }

    private function bulkData(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'integer'],
            'level_id' => ['required', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['integer'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer'],
        ]);
    }

    private function teacherData(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'integer'],
            'mode' => ['required', Rule::in(['teacher', 'class'])],
            'teacher_id' => ['exclude_unless:mode,teacher', 'required', 'integer'],
            'class_id' => ['exclude_unless:mode,class', 'required', 'integer'],
            'pairs' => ['exclude_unless:mode,teacher', 'array'],
            'pairs.*' => ['string', 'max:50'],
            'teachers' => ['exclude_unless:mode,class', 'array'],
            'teachers.*' => ['nullable', 'integer'],
        ]);
    }

    private function sectionFrom(Request $request): ?int
    {
        return $request->filled('section') ? $request->integer('section') : null;
    }
}
