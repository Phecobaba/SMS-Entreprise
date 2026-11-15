<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\LearnerTerminology;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\TeacherFacingLabel;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TeacherResultsWorkspace
{
    public function isTeacherWorkspace(User $actor, ?Request $request = null): bool
    {
        if (! app(ResultTeachingAssignments::class)->hasAssignments($actor)) {
            return false;
        }

        return ! (($request?->query('workspace') === 'review')
            && ($actor->can('approve_results') || $actor->can('manage_result_settings')));
    }

    public function landing(User $actor, ?Request $request = null): array
    {
        $term = app(AcademicCalendar::class)->currentTerm();
        $assignments = app(ResultTeachingAssignments::class)->assignedPairs($actor);
        $classIds = $assignments->pluck('class_id')->unique()->values();
        $arms = ClassArm::whereIn('class_id', $classIds)->orderBy('id')->get(['id', 'class_id', 'name']);
        $armToClass = $arms->pluck('class_id', 'id');
        $activePupils = Student::active()->whereIn('class_arm_id', $arms->pluck('id'))->get(['id', 'class_arm_id']);
        $pupilCounts = $activePupils->countBy('class_arm_id');

        $statusCounts = collect();
        if ($term && $arms->isNotEmpty() && $assignments->isNotEmpty()) {
            $statusCounts = Result::where('term_id', $term->id)
                ->whereIn('class_arm_id', $arms->pluck('id'))
                ->whereIn('student_id', $activePupils->pluck('id'))
                ->whereIn('subject_id', $assignments->pluck('subject_id')->unique())
                ->get(['class_arm_id', 'subject_id', 'status'])
                ->groupBy(fn (Result $result): string => $armToClass[$result->class_arm_id].':'.$result->subject_id)
                ->map(fn ($rows) => $rows->countBy('status'));
        }

        $pairs = $assignments->map(function (TeacherSubjectAssignment $assignment) use ($arms, $pupilCounts, $statusCounts): array {
            $pairArms = $arms->where('class_id', $assignment->class_id);
            $counts = $statusCounts->get($assignment->class_id.':'.$assignment->subject_id, collect());

            return [
                'class_id' => $assignment->class_id,
                'subject_id' => $assignment->subject_id,
                'label' => app(TeacherFacingLabel::class)->pair($assignment->subject?->name, $assignment->schoolClass?->name),
                'learnerTerm' => app(LearnerTerminology::class)->word($assignment->schoolClass),
                'learnersTerm' => app(LearnerTerminology::class)->word($assignment->schoolClass, true),
                'pupils' => (int) $pairArms->sum(fn (ClassArm $arm): int => (int) ($pupilCounts[$arm->id] ?? 0)),
                'draft' => (int) ($counts['Draft'] ?? 0),
                'submitted' => (int) ($counts['Submitted'] ?? 0),
                'approved' => (int) ($counts['Approved'] ?? 0),
            ];
        })->values();

        $returnedResults = null;
        $returnedNames = collect();
        $returnedLabels = collect();
        if ($request?->query('status') === 'Rejected') {
            $returnedQuery = Result::with('classArm.schoolClass')->where('status', 'Rejected');
            $returnedQuery->where(function (Builder $query) use ($assignments, $arms): void {
                foreach ($assignments as $assignment) {
                    $armIds = $arms->where('class_id', $assignment->class_id)->pluck('id');
                    $query->orWhere(fn (Builder $pair) => $pair
                        ->where('subject_id', $assignment->subject_id)
                        ->whereIn('class_arm_id', $armIds));
                }
                if ($assignments->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            });
            $returnedResults = $returnedQuery->latest('updated_at')->paginate(20)->withQueryString();
            $returnedNames = app(StudentDirectory::class)->names($returnedResults->pluck('student_id')->all());
            $subjects = $assignments->pluck('subject.name', 'subject_id');
            $returnedLabels = $returnedResults->mapWithKeys(fn (Result $result): array => [
                $result->id => app(TeacherFacingLabel::class)->pair(
                    $subjects[$result->subject_id] ?? null,
                    $result->classArm?->schoolClass?->name,
                ),
            ]);
        }

        return [
            'term' => $term,
            'termLabel' => $this->termLabel($term),
            'pairs' => $pairs,
            'formArms' => app(FormTeacherAssignments::class)->dashboardArms($actor),
            'returnedResults' => $returnedResults,
            'returnedNames' => $returnedNames,
            'returnedLabels' => $returnedLabels,
        ];
    }

    public function pairPage(User $actor, int $classId, int $subjectId, Request $request): array
    {
        $assignment = $this->assignment($actor, $classId, $subjectId);
        $term = app(AcademicCalendar::class)->currentTerm();
        $arms = ClassArm::where('class_id', $classId)->orderBy('name')->get();
        $activeStudentIds = Student::active()->whereIn('class_arm_id', $arms->pluck('id'))->pluck('id');
        $armId = $request->integer('arm') ?: null;
        if ($armId && ! $arms->contains('id', $armId)) {
            throw new AuthorizationException('That arm is outside this assigned class.');
        }

        $students = Student::active()->with(['user', 'classArm'])
            ->whereIn('class_arm_id', $arms->pluck('id'))
            ->when($armId, fn (Builder $query) => $query->where('class_arm_id', $armId))
            ->when(trim((string) $request->query('search')) !== '', function (Builder $query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function (Builder $part) use ($search): void {
                    $part->where('registration_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id')->paginate(20)->withQueryString();

        $results = collect();
        $counts = collect();
        if ($term) {
            $all = Result::where('term_id', $term->id)->where('subject_id', $subjectId)
                ->whereIn('class_arm_id', $arms->pluck('id'))->whereIn('student_id', $activeStudentIds)->get();
            $results = $all->keyBy('student_id');
            $counts = $all->countBy('status');
        }

        return [
            'assignment' => $assignment,
            'pairLabel' => app(TeacherFacingLabel::class)->pair($assignment->subject?->name, $assignment->schoolClass?->name),
            'term' => $term,
            'termLabel' => $this->termLabel($term),
            'arms' => $arms,
            'students' => $students,
            'resultsByStudent' => $results,
            'counts' => $counts,
            'totalPupils' => $activeStudentIds->count(),
            'learnerTerm' => app(LearnerTerminology::class)->word($assignment->schoolClass),
            'learnersTerm' => app(LearnerTerminology::class)->word($assignment->schoolClass, true),
        ];
    }

    public function entry(User $actor, int $classId, int $subjectId, int $studentId): array
    {
        $assignment = $this->assignment($actor, $classId, $subjectId);
        $armIds = ClassArm::where('class_id', $classId)->pluck('id');
        $student = Student::active()->with(['user', 'classArm.schoolClass'])
            ->whereKey($studentId)->whereIn('class_arm_id', $armIds)->first();
        if (! $student) {
            throw new AuthorizationException('That pupil is outside this assigned class.');
        }
        $term = app(AcademicCalendar::class)->currentTerm();
        $result = $term ? Result::with(['items.component', 'audit'])
            ->where('student_id', $student->id)->where('subject_id', $subjectId)->where('term_id', $term->id)->first() : null;
        $template = $result
            ? app(ResultTemplateResolver::class)->storedFor($result)
            : app(ResultTemplateResolver::class)->forClassArm($student->class_arm_id);

        return [
            'assignment' => $assignment,
            'pairLabel' => app(TeacherFacingLabel::class)->pair($assignment->subject?->name, $assignment->schoolClass?->name),
            'student' => $student,
            'term' => $term,
            'termLabel' => $this->termLabel($term),
            'result' => $result,
            'template' => $template,
            'templateMessage' => $template ? null : app(ResultWorkflow::class)->missingTemplateMessage($student->class_arm_id),
            'learnerTerm' => app(LearnerTerminology::class)->word($student),
            'learnersTerm' => app(LearnerTerminology::class)->word($student, true),
        ];
    }

    public function formClass(User $actor, int $armId): array
    {
        $arm = ClassArm::with('schoolClass')->whereKey($armId)->where('form_teacher_user_id', $actor->id)->first();
        if (! $arm) {
            throw new AuthorizationException('Only this arm’s form teacher may open My class.');
        }
        $term = app(AcademicCalendar::class)->currentTerm();
        $activeStudentIds = Student::active()->where('class_arm_id', $arm->id)->pluck('id');
        $cards = ResultCard::where('class_arm_id', $arm->id)
            ->whereIn('student_id', $activeStudentIds)
            ->when($term, fn (Builder $query) => $query->where('term_id', $term->id))
            ->when(! $term, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest('compiled_at')->paginate(20);
        $studentIds = $cards->pluck('student_id');
        $approvedCounts = $term ? Result::where('class_arm_id', $arm->id)->where('term_id', $term->id)
            ->where('status', 'Approved')->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, count(*) as aggregate')->groupBy('student_id')->pluck('aggregate', 'student_id') : collect();
        $names = Student::with('user')->whereIn('id', $studentIds)->get()
            ->mapWithKeys(fn (Student $student): array => [$student->id => $student->user?->name ?? 'Student #'.$student->id]);

        return [
            'arm' => $arm,
            'armLabel' => app(TeacherFacingLabel::class)->arm($arm->schoolClass?->name, $arm->name),
            'term' => $term,
            'termLabel' => $this->termLabel($term),
            'cards' => $cards,
            'approvedCounts' => $approvedCounts,
            'studentNames' => $names,
            'learnerTerm' => app(LearnerTerminology::class)->word($arm),
            'learnersTerm' => app(LearnerTerminology::class)->word($arm, true),
        ];
    }

    public function assertCurrentTerm(int $termId): void
    {
        $current = app(AcademicCalendar::class)->currentTerm();
        if (! $current || $current->id !== $termId) {
            throw new AuthorizationException('Teacher score entry is available for the current term only.');
        }
    }

    private function assignment(User $actor, int $classId, int $subjectId): TeacherSubjectAssignment
    {
        $assignment = app(ResultTeachingAssignments::class)->assignedPair($actor, $classId, $subjectId);
        if (! $assignment) {
            throw new AuthorizationException('You are not assigned to this subject and class.');
        }

        return $assignment;
    }

    private function termLabel($term): ?string
    {
        if (! $term) {
            return null;
        }

        return trim(($term->name ?? 'Term').' '.($term->session?->name ?? ''));
    }
}
