<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\SchoolUserDirectory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResultTeachingAssignments
{
    private array $assignmentPresence = [];

    public function dashboardTeacherContexts(User $actor): Collection
    {
        return $this->assignedPairOptions($actor)->pluck('label');
    }

    public function dashboardTeacherCount(?User $viewer = null, ?int $sectionId = null): int
    {
        $query = TeacherSubjectAssignment::query();
        if ($viewer) {
            $visibleClassIds = app(SectionAccess::class)->scopeClasses(SchoolClass::query(), $viewer, $sectionId)->select('id');
            $query->whereIn('class_id', $visibleClassIds);
        }

        return $query->distinct()->count('user_id');
    }

    public function assertMayEnterClass(User $actor, int $classId): void
    {
        if (! TeacherSubjectAssignment::where('user_id', $actor->id)->where('class_id', $classId)->exists()) {
            throw ValidationException::withMessages(['class_id' => 'You are not assigned to this class.']);
        }
    }

    public function assign(int $userId, int $classId, int $subjectId, User $actor): TeacherSubjectAssignment
    {
        $user = app(SchoolUserDirectory::class)->find($userId);
        $class = SchoolClass::with('level')->findOrFail($classId);
        if (! $user || ! $class->level?->section_id || ! app(SectionTeacherDirectory::class)->isEligible(
            $user,
            (int) $class->level->section_id,
            SectionTeacherDirectory::SUBJECT_PERMISSIONS,
            $actor->can('view_all_sections'),
        )) {
            throw ValidationException::withMessages(['user_id' => 'Choose an active teacher allowed for this section.']);
        }
        app(SubjectReferences::class)->assertClassSubjectSection($classId, $subjectId);
        if (! ClassSubject::where('class_id', $classId)->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Assign the subject to this class first.']);
        }
        if (TeacherSubjectAssignment::where('class_id', $classId)->where('subject_id', $subjectId)->where('user_id', '!=', $userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'This class subject already has a teacher. Remove that assignment before choosing another teacher.']);
        }

        return TeacherSubjectAssignment::firstOrCreate(['user_id' => $userId, 'class_id' => $classId, 'subject_id' => $subjectId]);
    }

    public function assertMayEnter(User $actor, int $classId, int $subjectId): void
    {
        if (! $this->mayEnter($actor, $classId, $subjectId)) {
            throw new AuthorizationException('You are not assigned to enter this class’s subject results.');
        }
    }

    public function mayEnter(User $actor, int $classId, int $subjectId): bool
    {
        return Gate::forUser($actor)->allows('enter_results')
            && TeacherSubjectAssignment::where('user_id', $actor->id)->where('class_id', $classId)->where('subject_id', $subjectId)->exists();
    }

    public function hasAssignments(User $actor): bool
    {
        return $this->assignmentPresence[$actor->id]
            ??= TeacherSubjectAssignment::where('user_id', $actor->id)->exists();
    }

    public function unassignedPairOptions(User $actor): Collection
    {
        $classIds = app(SectionAccess::class)->scopeClasses(SchoolClass::query(), $actor)->select('id');

        return ClassSubject::with(['schoolClass', 'subject'])
            ->whereIn('class_id', $classIds)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('teacher_subject_assignments')
                    ->whereColumn('teacher_subject_assignments.class_id', 'class_subjects.class_id')
                    ->whereColumn('teacher_subject_assignments.subject_id', 'class_subjects.subject_id');
            })
            ->orderBy('class_id')->orderBy('subject_id')->limit(100)->get()
            ->map(fn (ClassSubject $pair): array => [
                'class_id' => $pair->class_id,
                'subject_id' => $pair->subject_id,
                'label' => app(TeacherFacingLabel::class)->pair($pair->subject?->name, $pair->schoolClass?->name),
            ]);
    }

    public function pairsFor(User $actor): array
    {
        return TeacherSubjectAssignment::where('user_id', $actor->id)->get(['class_id', 'subject_id'])
            ->map(fn ($row) => ['class_id' => $row->class_id, 'subject_id' => $row->subject_id])->all();
    }

    public function assignedPairOptions(User $actor): Collection
    {
        return $this->assignedPairs($actor)
            ->map(fn (TeacherSubjectAssignment $assignment): array => [
                'class_id' => $assignment->class_id,
                'subject_id' => $assignment->subject_id,
                'value' => $assignment->class_id.':'.$assignment->subject_id,
                'label' => app(TeacherFacingLabel::class)->pair($assignment->subject?->name, $assignment->schoolClass?->name),
            ]);
    }

    public function assignedPairs(User $actor): Collection
    {
        return TeacherSubjectAssignment::with(['schoolClass.level.section', 'subject'])
            ->where('user_id', $actor->id)
            ->orderBy('class_id')->orderBy('subject_id')->get();
    }

    public function assignedPair(User $actor, int $classId, int $subjectId): ?TeacherSubjectAssignment
    {
        return TeacherSubjectAssignment::with(['schoolClass.level.section', 'subject'])
            ->where('user_id', $actor->id)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->first();
    }

    public function pairOptionsFor(User $actor, string $managePermission): Collection
    {
        if (! Gate::forUser($actor)->allows($managePermission)) {
            return $this->assignedPairOptions($actor);
        }

        return ClassSubject::with(['schoolClass', 'subject'])->orderBy('class_id')->orderBy('subject_id')->get()
            ->map(fn (ClassSubject $pair): array => [
                'class_id' => $pair->class_id,
                'subject_id' => $pair->subject_id,
                'value' => $pair->class_id.':'.$pair->subject_id,
                'label' => app(TeacherFacingLabel::class)->pair($pair->subject?->name, $pair->schoolClass?->name),
            ]);
    }

    public function resultScopesFor(User $actor): array
    {
        $pairs = $this->pairsFor($actor);
        $classIds = array_values(array_unique(array_column($pairs, 'class_id')));
        $armsByClass = ClassArm::whereIn('class_id', $classIds)->get(['id', 'class_id'])->groupBy('class_id');

        return array_map(fn (array $pair) => [
            'subject_id' => $pair['subject_id'],
            'arm_ids' => $armsByClass->get($pair['class_id'], collect())->pluck('id')->all(),
        ], $pairs);
    }

    public function armIdsFor(User $actor): array
    {
        $classIds = TeacherSubjectAssignment::where('user_id', $actor->id)->distinct()->pluck('class_id');

        return ClassArm::whereIn('class_id', $classIds)->pluck('id')->all();
    }

    public function subjectsForArm(User $actor, int $armId): array
    {
        $arm = ClassArm::findOrFail($armId);
        $query = Subject::whereIn('id', ClassSubject::where('class_id', $arm->class_id)->select('subject_id'));
        $query->whereIn('id', TeacherSubjectAssignment::where('user_id', $actor->id)->where('class_id', $arm->class_id)->select('subject_id'));

        return $query->orderBy('name')->get()->all();
    }

    public function paginated()
    {
        return TeacherSubjectAssignment::orderBy('class_id')->orderBy('subject_id')->paginate(15, ['*'], 'assignments_page');
    }
}
