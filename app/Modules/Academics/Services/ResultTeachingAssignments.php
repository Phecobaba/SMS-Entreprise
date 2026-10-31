<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\SchoolUserDirectory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResultTeachingAssignments
{
    public function dashboardTeacherContext(User $actor): ?array
    {
        $assignment = TeacherSubjectAssignment::where('user_id', $actor->id)->orderBy('id')->first();
        if (! $assignment) {
            return null;
        }
        $class = SchoolClass::find($assignment->class_id);
        $subject = Subject::find($assignment->subject_id);

        return ['class' => $class?->name, 'subject' => $subject?->name];
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
        if (! Gate::forUser($actor)->allows('manage_result_settings')
            && ! TeacherSubjectAssignment::where('user_id', $actor->id)->where('class_id', $classId)->exists()) {
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
            throw ValidationException::withMessages(['subject_id' => 'You are not assigned to enter this class’s subject results.']);
        }
    }

    public function mayEnter(User $actor, int $classId, int $subjectId): bool
    {
        return Gate::forUser($actor)->allows('manage_result_settings')
            || TeacherSubjectAssignment::where('user_id', $actor->id)->where('class_id', $classId)->where('subject_id', $subjectId)->exists();
    }

    public function pairsFor(User $actor): array
    {
        return TeacherSubjectAssignment::where('user_id', $actor->id)->get(['class_id', 'subject_id'])
            ->map(fn ($row) => ['class_id' => $row->class_id, 'subject_id' => $row->subject_id])->all();
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
        if (! Gate::forUser($actor)->allows('manage_result_settings')) {
            $query->whereIn('id', TeacherSubjectAssignment::where('user_id', $actor->id)->where('class_id', $arm->class_id)->select('subject_id'));
        }

        return $query->orderBy('name')->get()->all();
    }

    public function paginated()
    {
        return TeacherSubjectAssignment::orderBy('class_id')->orderBy('subject_id')->paginate(15, ['*'], 'assignments_page');
    }
}
