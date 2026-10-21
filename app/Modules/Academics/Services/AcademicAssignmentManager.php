<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeacherSubjectAssignment;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicAssignmentManager
{
    public const SUBJECT_PERMISSION = 'manage_section_subjects';

    public const TEACHER_PERMISSION = 'manage_section_teacher_assignments';

    public function scope(User $actor, string $area, ?int $requestedSectionId = null): array
    {
        $adminPermission = $area === 'subjects' ? 'manage_academics' : 'manage_result_settings';
        $headPermission = $area === 'subjects' ? self::SUBJECT_PERMISSION : self::TEACHER_PERMISSION;
        $canViewAll = $actor->can('view_all_sections') && $actor->can($adminPermission);

        if (! $canViewAll && ! $actor->can($headPermission)) {
            throw new AuthorizationException('You do not have access to this assignment page.');
        }

        if ($canViewAll) {
            $sections = Section::orderBy('name')->limit(100)->get();
            $sectionId = $requestedSectionId ?: $sections->first()?->id;
            if ($sectionId !== null) {
                app(SectionAccess::class)->assertSection($actor, $sectionId);
            }
        } else {
            $sectionIds = app(SectionAccess::class)->sectionIds($actor);
            $sections = Section::whereIn('id', $sectionIds)->orderBy('name')->limit(100)->get();
            $sectionId = $sections->first()?->id;
            if ($requestedSectionId !== null && $requestedSectionId !== $sectionId) {
                throw new AuthorizationException('You cannot switch to another section.');
            }
        }

        return [
            'canViewAll' => $canViewAll,
            'sections' => $sections,
            'section' => $sectionId ? $sections->firstWhere('id', $sectionId) : null,
            'sectionId' => $sectionId,
            'hasSection' => $canViewAll || $sectionId !== null,
        ];
    }

    public function catalog(User $actor, string $area, ?int $requestedSectionId = null): array
    {
        $scope = $this->scope($actor, $area, $requestedSectionId);
        $sectionId = $scope['sectionId'];
        $levels = collect();
        $classes = collect();

        if ($sectionId) {
            $levels = AcademicLevel::where('section_id', $sectionId)
                ->with(['classes' => fn ($query) => $query->with(['department', 'classSubjects.subject'])
                    ->orderByRaw('position is null')->orderBy('position')->orderBy('name')])
                ->orderBy('name')->limit(100)->get();
            $classes = $levels->flatMap->classes->values();
        }

        return [
            ...$scope,
            'levels' => $levels,
            'classes' => $classes,
            'subjects' => Subject::orderBy('name')->limit(100)->get(),
        ];
    }

    public function teacherCatalog(User $actor, ?int $requestedSectionId = null): array
    {
        $catalog = $this->catalog($actor, 'teachers', $requestedSectionId);
        $catalog['teachers'] = User::where('status', 'active')
            ->whereHas('roles.permissions', fn (Builder $query) => $query->whereIn('permissions.name', [
                'enter_results', 'mark_assignments', 'upload_enotes',
            ]))
            ->orderBy('name')->limit(100)->get(['id', 'name', 'email']);
        $catalog['assignments'] = TeacherSubjectAssignment::whereIn('class_id', $catalog['classes']->pluck('id'))
            ->get(['id', 'user_id', 'class_id', 'subject_id']);

        return $catalog;
    }

    public function previewBulkSubjects(User $actor, int $sectionId, int $levelId, ?int $departmentId, array $classIds, array $subjectIds): array
    {
        return $this->bulkSubjectPlan($actor, $sectionId, $levelId, $departmentId, $classIds, $subjectIds);
    }

    public function applyBulkSubjects(User $actor, int $sectionId, int $levelId, ?int $departmentId, array $classIds, array $subjectIds): array
    {
        return DB::transaction(function () use ($actor, $sectionId, $levelId, $departmentId, $classIds, $subjectIds): array {
            $plan = $this->bulkSubjectPlan($actor, $sectionId, $levelId, $departmentId, $classIds, $subjectIds, true);
            foreach ($plan['pairsToAdd'] as $pair) {
                ClassSubject::firstOrCreate($pair);
            }

            return collect($plan)->except('pairsToAdd')->all();
        });
    }

    public function previewTeacher(User $actor, int $sectionId, int $teacherId, array $pairKeys): array
    {
        return $this->teacherPlan($actor, $sectionId, $teacherId, $pairKeys);
    }

    public function applyTeacher(User $actor, int $sectionId, int $teacherId, array $pairKeys): array
    {
        return DB::transaction(function () use ($actor, $sectionId, $teacherId, $pairKeys): array {
            $plan = $this->teacherPlan($actor, $sectionId, $teacherId, $pairKeys, true);
            TeacherSubjectAssignment::whereIn('id', collect($plan['removeRows'])->pluck('id'))->delete();
            foreach ($plan['addPairs'] as $pair) {
                TeacherSubjectAssignment::firstOrCreate(['user_id' => $teacherId, ...$pair]);
            }

            return collect($plan)->except(['removeRows', 'addPairs'])->all();
        });
    }

    public function previewClass(User $actor, int $sectionId, int $classId, array $teacherBySubject): array
    {
        return $this->classPlan($actor, $sectionId, $classId, $teacherBySubject);
    }

    public function applyClass(User $actor, int $sectionId, int $classId, array $teacherBySubject): array
    {
        return DB::transaction(function () use ($actor, $sectionId, $classId, $teacherBySubject): array {
            $plan = $this->classPlan($actor, $sectionId, $classId, $teacherBySubject, true);
            TeacherSubjectAssignment::whereIn('id', collect($plan['removeRows'])->pluck('id'))->delete();
            foreach ($plan['addPairs'] as $pair) {
                TeacherSubjectAssignment::firstOrCreate($pair);
            }

            return collect($plan)->except(['removeRows', 'addPairs'])->all();
        });
    }

    private function bulkSubjectPlan(User $actor, int $sectionId, int $levelId, ?int $departmentId, array $classIds, array $subjectIds, bool $lock = false): array
    {
        $this->scope($actor, 'subjects', $sectionId);
        $level = AcademicLevel::whereKey($levelId)->where('section_id', $sectionId)->first();
        if (! $level) {
            throw new AuthorizationException('Choose a level in your section.');
        }

        if (! SchoolClass::where('academic_level_id', $levelId)->whereNotNull('department_id')->exists()) {
            $departmentId = null;
        }

        $classIds = $this->ids($classIds);
        $subjectIds = $this->ids($subjectIds);
        if ($classIds === []) {
            throw ValidationException::withMessages(['class_ids' => 'Choose at least one class.']);
        }
        if ($subjectIds === []) {
            throw ValidationException::withMessages(['subject_ids' => 'Choose at least one subject.']);
        }

        $classQuery = SchoolClass::where('academic_level_id', $levelId)->whereIn('id', $classIds);
        if ($departmentId !== null) {
            $classQuery->where('department_id', $departmentId);
        }
        $classes = $classQuery->orderBy('name')->get(['id', 'name']);
        if ($classes->count() !== count($classIds)) {
            throw new AuthorizationException('Every selected class must belong to the chosen level and section.');
        }
        $subjects = Subject::whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name']);
        if ($subjects->count() !== count($subjectIds)) {
            throw new AuthorizationException('One or more selected subjects do not belong to this school.');
        }

        $existingQuery = ClassSubject::whereIn('class_id', $classIds)->whereIn('subject_id', $subjectIds);
        if ($lock) {
            $existingQuery->lockForUpdate();
        }
        $existing = $existingQuery->get()->keyBy(fn ($row) => $row->class_id.':'.$row->subject_id);
        $pairsToAdd = [];
        $perClass = [];
        foreach ($classes as $class) {
            $add = 0;
            $skip = 0;
            foreach ($subjects as $subject) {
                if ($existing->has($class->id.':'.$subject->id)) {
                    $skip++;
                } else {
                    $add++;
                    $pairsToAdd[] = ['class_id' => $class->id, 'subject_id' => $subject->id];
                }
            }
            $perClass[] = ['class' => $class->name, 'add' => $add, 'skipped' => $skip];
        }

        return [
            'added' => count($pairsToAdd),
            'skipped' => $existing->count(),
            'failed' => 0,
            'classes' => $classes->count(),
            'perClass' => $perClass,
            'pairsToAdd' => $pairsToAdd,
        ];
    }

    private function teacherPlan(User $actor, int $sectionId, int $teacherId, array $pairKeys, bool $lock = false): array
    {
        $catalog = $this->teacherCatalog($actor, $sectionId);
        $teacher = $catalog['teachers']->firstWhere('id', $teacherId);
        if (! $teacher) {
            throw ValidationException::withMessages(['teacher_id' => 'Choose a teacher.']);
        }

        $offered = $this->offeredPairs($catalog['classes']);
        $desired = collect($pairKeys)->map(fn ($key) => $this->parsePair((string) $key))->filter()
            ->filter(fn (array $pair) => $offered->has($pair['class_id'].':'.$pair['subject_id']))
            ->keyBy(fn (array $pair) => $pair['class_id'].':'.$pair['subject_id']);
        $this->assertNoOutsideClasses($actor, $sectionId, $pairKeys);

        $existingQuery = TeacherSubjectAssignment::where('user_id', $teacherId)->whereIn('class_id', $catalog['classes']->pluck('id'));
        if ($lock) {
            $existingQuery->lockForUpdate();
        }
        $existing = $existingQuery->get()->keyBy(fn ($row) => $row->class_id.':'.$row->subject_id);
        $existingByPair = collect($existing->all());
        $addPairs = $desired->except($existing->keys())->values();
        $removeRows = $existingByPair->except($desired->keys())->values();
        $this->assertPairsUnassigned($teacherId, $addPairs);

        return $this->teacherPlanResult($addPairs, $removeRows, $desired->intersectByKeys($existingByPair)->count(), $catalog, $teacher->name);
    }

    private function classPlan(User $actor, int $sectionId, int $classId, array $teacherBySubject, bool $lock = false): array
    {
        $catalog = $this->teacherCatalog($actor, $sectionId);
        $class = $catalog['classes']->firstWhere('id', $classId);
        if (! $class) {
            throw new AuthorizationException('Choose a class in your section.');
        }
        $teacherIds = $catalog['teachers']->pluck('id')->map(fn ($id) => (int) $id)->all();
        $offeredIds = $class->classSubjects->pluck('subject_id')->map(fn ($id) => (int) $id);
        $desired = collect($teacherBySubject)->mapWithKeys(function ($teacherId, $subjectId) use ($offeredIds, $teacherIds): array {
            $subjectId = (int) $subjectId;
            if (! $offeredIds->contains($subjectId)) {
                return [];
            }
            $teacherId = $teacherId === null || $teacherId === '' ? null : (int) $teacherId;
            if ($teacherId !== null && ! in_array($teacherId, $teacherIds, true)) {
                throw ValidationException::withMessages(['teachers' => 'Choose a teacher from this school.']);
            }

            return [$subjectId => $teacherId];
        });

        $existingQuery = TeacherSubjectAssignment::where('class_id', $classId)->whereIn('subject_id', $offeredIds);
        if ($lock) {
            $existingQuery->lockForUpdate();
        }
        $existing = $existingQuery->get()->groupBy('subject_id');
        $addPairs = collect();
        $removeRows = collect();
        $unchanged = 0;
        foreach ($offeredIds as $subjectId) {
            $rows = $existing->get($subjectId, collect());
            $wanted = $desired->get($subjectId);
            if ($wanted === null) {
                $removeRows = $removeRows->merge($rows);
            } elseif ($rows->contains('user_id', $wanted)) {
                $unchanged++;
            } elseif ($rows->isNotEmpty()) {
                throw ValidationException::withMessages(['teachers' => 'That subject already has a teacher. Remove the current teacher and save before assigning another.']);
            } else {
                $addPairs->push(['user_id' => $wanted, 'class_id' => $classId, 'subject_id' => $subjectId]);
            }
        }

        return $this->teacherPlanResult($addPairs, $removeRows, $unchanged, $catalog, $class->name);
    }

    private function teacherPlanResult(Collection $addPairs, Collection $removeRows, int $unchanged, array $catalog, string $label): array
    {
        $classes = $catalog['classes']->keyBy('id');
        $subjects = $catalog['subjects']->keyBy('id');
        $teachers = $catalog['teachers']->keyBy('id');
        $changes = $addPairs->map(fn (array $pair) => [
            'type' => 'add',
            'class' => $classes->get($pair['class_id'])?->name,
            'subject' => $subjects->get($pair['subject_id'])?->name,
            'teacher' => isset($pair['user_id']) ? $teachers->get($pair['user_id'])?->name : $label,
        ])->merge($removeRows->map(fn ($row) => [
            'type' => 'remove',
            'class' => $classes->get($row->class_id)?->name,
            'subject' => $subjects->get($row->subject_id)?->name,
            'teacher' => $teachers->get($row->user_id)?->name ?? $label,
        ]))->values()->all();

        return [
            'added' => $addPairs->count(),
            'removed' => $removeRows->count(),
            'unchanged' => $unchanged,
            'label' => $label,
            'changes' => $changes,
            'addPairs' => $addPairs->values()->all(),
            'removeRows' => $removeRows->values(),
        ];
    }

    private function offeredPairs(Collection $classes): Collection
    {
        return $classes->flatMap(fn (SchoolClass $class) => $class->classSubjects->map(fn (ClassSubject $item) => [
            'class_id' => $class->id,
            'subject_id' => $item->subject_id,
        ]))->keyBy(fn (array $pair) => $pair['class_id'].':'.$pair['subject_id']);
    }

    private function assertPairsUnassigned(int $teacherId, Collection $pairs): void
    {
        foreach ($pairs as $pair) {
            if (TeacherSubjectAssignment::where('class_id', $pair['class_id'])->where('subject_id', $pair['subject_id'])
                ->where('user_id', '!=', $teacherId)->exists()) {
                throw ValidationException::withMessages(['pairs' => 'One selected class subject already has a teacher. Remove that assignment first.']);
            }
        }
    }

    private function assertNoOutsideClasses(User $actor, int $sectionId, array $pairKeys): void
    {
        foreach ($pairKeys as $key) {
            $pair = $this->parsePair((string) $key);
            if ($pair && ! SchoolClass::whereKey($pair['class_id'])->whereHas('level', fn (Builder $query) => $query->where('section_id', $sectionId))->exists()) {
                throw new AuthorizationException('You cannot assign a teacher outside your section.');
            }
        }
    }

    private function parsePair(string $key): ?array
    {
        if (! preg_match('/^(\d+):(\d+)$/', $key, $matches)) {
            return null;
        }

        return ['class_id' => (int) $matches[1], 'subject_id' => (int) $matches[2]];
    }

    private function ids(array $values): array
    {
        return collect($values)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
    }
}
