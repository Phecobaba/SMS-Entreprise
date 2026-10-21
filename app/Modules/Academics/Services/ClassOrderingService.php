<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassOrderingService
{
    public function mayManage(User $actor): bool
    {
        return ($actor->can('manage_academics') && $actor->can('view_all_sections'))
            || $actor->can('manage_section_classes');
    }

    public function assertMayManageClass(User $actor, SchoolClass $class): void
    {
        $class->loadMissing('level.section');
        $this->authorizeSection($actor, $this->requiredSection($class->level));
    }

    public function assertMayManageLevel(User $actor, AcademicLevel $level): void
    {
        $level->loadMissing('section');
        $this->authorizeSection($actor, $this->requiredSection($level));
    }

    public function sectionIsArranged(Section|int $section): bool
    {
        $section = $section instanceof Section ? $section : Section::findOrFail($section);

        return $section->classes_arranged_at !== null;
    }

    public function everySectionIsArranged(): bool
    {
        return ! Section::whereNull('classes_arranged_at')->exists();
    }

    public function comesAfterOptions(User $actor, AcademicLevel $level, ?int $exceptClassId = null): Collection
    {
        app(SectionAccess::class)->assertLevel($actor, $level->id);
        $query = app(SectionAccess::class)->scopeClasses(
            SchoolClass::where('academic_level_id', $level->id),
            $actor,
        );
        if ($exceptClassId) {
            $query->whereKeyNot($exceptClassId);
        }

        $classes = $query->get();

        return $classes->sort(function (SchoolClass $left, SchoolClass $right): int {
            if ($left->position !== null && $right->position !== null && $left->position !== $right->position) {
                return $left->position <=> $right->position;
            }

            return strnatcasecmp($left->name, $right->name);
        })->values();
    }

    public function create(array $data, User $actor): SchoolClass
    {
        $level = AcademicLevel::with('section')->findOrFail($data['academic_level_id']);
        $section = $this->requiredSection($level);
        $this->authorizeSection($actor, $section);

        return DB::transaction(function () use ($data, $actor, $level, $section): SchoolClass {
            SchoolClass::lockForUpdate()->get(['id']);
            $class = SchoolClass::create([
                'academic_level_id' => $level->id,
                'department_id' => $data['department_id'] ?? null,
                'name' => trim($data['name']),
                'position' => null,
            ]);

            $boundaryChoice = null;
            if ($this->sectionIsArranged($section)) {
                $boundaryChoice = $this->place($class, $level, $data['comes_after_id'] ?? null, $data['placement'] ?? 'new');
            }

            app(AuditRecorder::class)->record($actor, 'class_created', $class, [
                'level_id' => $level->id,
                'department_id' => $class->department_id,
                'position' => $class->position,
                'boundary_choice' => $boundaryChoice,
            ]);

            return $class->refresh();
        });
    }

    public function update(SchoolClass $class, array $data, User $actor): SchoolClass
    {
        $class->loadMissing('level.section');
        $this->authorizeSection($actor, $this->requiredSection($class->level));
        $level = AcademicLevel::with('section')->findOrFail($data['academic_level_id']);
        $targetSection = $this->requiredSection($level);
        $this->authorizeSection($actor, $targetSection);

        return DB::transaction(function () use ($class, $data, $actor, $level, $targetSection): SchoolClass {
            SchoolClass::lockForUpdate()->get(['id']);
            $before = $class->only(['academic_level_id', 'department_id', 'name', 'position']);
            $levelChanged = $class->academic_level_id !== $level->id;
            $positionChanged = array_key_exists('comes_after_id', $data) || $levelChanged;

            if ($positionChanged && $class->position !== null) {
                $this->removeStepMember($class);
            }

            $class->update([
                'academic_level_id' => $level->id,
                'department_id' => $data['department_id'] ?? null,
                'name' => trim($data['name']),
                'position' => $positionChanged ? null : $class->position,
            ]);

            if ($positionChanged && $this->sectionIsArranged($targetSection)) {
                $this->place($class, $level, $data['comes_after_id'] ?? null, $data['placement'] ?? 'new');
            }

            app(AuditRecorder::class)->record($actor, $levelChanged ? 'class_level_changed' : 'class_updated', $class, [
                'before' => $before,
                'after' => $class->fresh()->only(['academic_level_id', 'department_id', 'name', 'position']),
            ]);

            return $class->refresh();
        });
    }

    public function move(SchoolClass $class, string $direction, User $actor): SchoolClass
    {
        $class->loadMissing('level.section');
        $this->authorizeSection($actor, $this->requiredSection($class->level));
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => 'Choose Move up or Move down.']);
        }

        return DB::transaction(function () use ($class, $direction, $actor): SchoolClass {
            SchoolClass::lockForUpdate()->get(['id']);
            $class->refresh();
            if ($class->position === null) {
                throw ValidationException::withMessages(['class' => 'Arrange this section before moving classes.']);
            }
            $steps = SchoolClass::where('academic_level_id', $class->academic_level_id)
                ->whereNotNull('position')->select('position')->distinct()->orderBy('position')->pluck('position')->all();
            $index = array_search($class->position, $steps, true);
            $adjacent = $direction === 'up' ? $steps[$index - 1] ?? null : $steps[$index + 1] ?? null;
            if ($adjacent === null) {
                throw ValidationException::withMessages(['class' => 'This class is already at the edge of its level.']);
            }

            $from = (int) $class->position;
            $temporary = 4294967294;
            SchoolClass::where('academic_level_id', $class->academic_level_id)->where('position', $from)->update(['position' => $temporary]);
            SchoolClass::where('academic_level_id', $class->academic_level_id)->where('position', $adjacent)->update(['position' => $from]);
            SchoolClass::where('academic_level_id', $class->academic_level_id)->where('position', $temporary)->update(['position' => $adjacent]);

            app(AuditRecorder::class)->record($actor, 'class_step_moved', $class, [
                'direction' => $direction,
                'from' => $from,
                'to' => (int) $adjacent,
                'class_ids' => SchoolClass::where('academic_level_id', $class->academic_level_id)
                    ->where('position', $adjacent)->pluck('id')->all(),
            ]);

            return $class->refresh();
        });
    }

    public function delete(SchoolClass $class, User $actor): void
    {
        $class->loadMissing('level.section');
        $this->authorizeSection($actor, $this->requiredSection($class->level));
        $usage = app(ClassHistoryUsage::class)->counts($class);
        if (array_sum($usage) > 0) {
            $parts = collect($usage)->filter()->map(fn (int $count, string $type) => $count.' '.$type)->values()->join(', ');
            throw ValidationException::withMessages([
                'class' => $class->name.' has '.$parts.', so it cannot be deleted. Move students through Promotion and keep school history intact.',
            ]);
        }

        DB::transaction(function () use ($class, $actor): void {
            SchoolClass::lockForUpdate()->get(['id']);
            $position = $class->position;
            app(AuditRecorder::class)->record($actor, 'class_deleted', $class, [
                'name' => $class->name,
                'level_id' => $class->academic_level_id,
                'position' => $position,
            ]);
            $class->delete();
            if ($position !== null && ! SchoolClass::where('position', $position)->exists()) {
                SchoolClass::where('position', '>', $position)->decrement('position');
            }
        });
    }

    public function arrangementFor(Section $section): array
    {
        $section->loadMissing(['levels.classes.department']);
        $levels = $section->levels->sort(fn (AcademicLevel $a, AcademicLevel $b) => strnatcasecmp($a->name, $b->name));

        return $levels->map(function (AcademicLevel $level) use ($section): array {
            $classes = $level->classes;
            if ($this->sectionIsArranged($section)) {
                $steps = $classes->sortBy('position')->groupBy('position')->values();
            } else {
                $steps = $classes->sort(fn (SchoolClass $a, SchoolClass $b) => strnatcasecmp($a->name, $b->name))
                    ->map(fn (SchoolClass $class) => collect([$class]))->values();
            }

            return ['level' => $level, 'steps' => $steps];
        })->all();
    }

    public function saveArrangement(array $submitted, User $actor): void
    {
        if (! $this->mayManage($actor)) {
            throw new AuthorizationException('You do not have permission to arrange classes.');
        }
        $allowedIds = $actor->can('view_all_sections')
            ? Section::orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : app(SectionAccess::class)->sectionIds($actor);
        $orderedAllowedIds = $allowedIds;
        $submittedIds = array_map('intval', array_keys($submitted));
        sort($allowedIds);
        sort($submittedIds);
        if ($allowedIds !== $submittedIds) {
            throw new AuthorizationException('You cannot arrange another section, and every section in your scope must be submitted.');
        }

        $orders = [];
        foreach ($orderedAllowedIds as $sectionId) {
            app(SectionAccess::class)->assertSection($actor, $sectionId);
            $orders[$sectionId] = $this->validatedSubmittedSteps($sectionId, $submitted[$sectionId] ?? []);
        }

        DB::transaction(function () use ($orders, $actor): void {
            SchoolClass::lockForUpdate()->get(['id']);
            Section::whereIn('id', array_keys($orders))->lockForUpdate()->get(['id']);

            $targetClassIds = collect($orders)->flatten(2)->map(fn ($id) => (int) $id)->all();
            $targetMinimum = SchoolClass::whereIn('id', $targetClassIds)->min('position');
            $existingByPosition = SchoolClass::whereNotNull('position')->whereNotIn('id', $targetClassIds)
                ->orderBy('position')->get()->groupBy('position');
            $existingSteps = $existingByPosition->values()
                ->map(fn (Collection $classes) => $classes->pluck('id')->map(fn ($id) => (int) $id)->all())->all();
            $submittedSteps = collect($orders)->flatten(1)->values()->all();
            $insertAt = $targetMinimum === null
                ? count($existingSteps)
                : $existingByPosition->keys()->filter(fn ($position) => (int) $position < (int) $targetMinimum)->count();
            array_splice($existingSteps, $insertAt, 0, $submittedSteps);

            foreach ($orders as $sectionId => $steps) {
                Section::whereKey($sectionId)->update([
                    'classes_arranged_by' => $actor->id,
                    'classes_arranged_at' => now(),
                ]);
            }

            foreach ($existingSteps as $index => $step) {
                SchoolClass::whereIn('id', $step)->update(['position' => $index + 1]);
            }

            foreach (array_keys($orders) as $sectionId) {
                app(AuditRecorder::class)->record($actor, 'class_arrangement_saved', Section::findOrFail($sectionId), [
                    'steps' => $orders[$sectionId],
                ]);
            }
        });
    }

    private function validatedSubmittedSteps(int $sectionId, array $rawSteps): array
    {
        $steps = collect($rawSteps)->map(function ($raw): array {
            $ids = is_array($raw) ? $raw : explode(',', (string) $raw);

            return collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        })->filter()->values()->all();
        $submitted = collect($steps)->flatten()->values();
        $expected = SchoolClass::whereHas('level', fn ($query) => $query->where('section_id', $sectionId))
            ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        if ($submitted->count() !== $submitted->unique()->count() || $submitted->sort()->values()->all() !== $expected->all()) {
            throw ValidationException::withMessages(['steps' => 'The class order changed while you were arranging it. Reload and try again.']);
        }

        foreach ($steps as $step) {
            $classes = SchoolClass::whereIn('id', $step)->get();
            if ($classes->pluck('academic_level_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['steps' => 'Classes can move only inside their own level.']);
            }
            $this->assertValidParallelDepartments($classes);
        }

        return $steps;
    }

    private function place(SchoolClass $class, AcademicLevel $level, ?int $comesAfterId, string $placement): ?string
    {
        $target = null;
        if ($comesAfterId) {
            $target = SchoolClass::whereKey($comesAfterId)->where('academic_level_id', $level->id)->first();
            if (! $target || $target->is($class)) {
                throw ValidationException::withMessages(['comes_after_id' => 'Choose a class in the selected level.']);
            }
        }

        if ($placement === 'same') {
            if (! Department::exists() || ! $target || $target->position === null) {
                throw ValidationException::withMessages(['placement' => 'Same step is available only for an arranged level with departments.']);
            }
            $parallel = SchoolClass::where('position', $target->position)->get();
            $this->assertValidParallelDepartments($parallel->push($class));
            $class->update(['position' => $target->position]);

            return null;
        }

        if ($placement !== 'new') {
            throw ValidationException::withMessages(['placement' => 'Choose where this class should go.']);
        }

        $boundaryChoice = null;
        if ($target) {
            if ($target->position === null) {
                throw ValidationException::withMessages(['comes_after_id' => 'Arrange this section before choosing a saved position.']);
            }
            $position = (int) $target->position + 1;
        } else {
            $position = $this->firstPositionForLevel($level);
            if (! SchoolClass::whereHas('level', fn ($query) => $query->where('section_id', $level->section_id))
                ->whereKeyNot($class->id)->whereNotNull('position')->exists()) {
                $boundaryChoice = 'Sections have no stored order, so the first class was placed at the end of the school sequence.';
            }
        }
        SchoolClass::where('position', '>=', $position)->increment('position');
        $class->update(['position' => $position]);

        return $boundaryChoice;
    }

    private function firstPositionForLevel(AcademicLevel $level): int
    {
        $levels = AcademicLevel::where('section_id', $level->section_id)->get()
            ->sort(fn (AcademicLevel $a, AcademicLevel $b) => strnatcasecmp($a->name, $b->name))->values();
        $index = $levels->search(fn (AcademicLevel $candidate) => $candidate->id === $level->id);
        for ($i = $index - 1; $i >= 0; $i--) {
            $last = SchoolClass::where('academic_level_id', $levels[$i]->id)->max('position');
            if ($last !== null) {
                return (int) $last + 1;
            }
        }
        $firstInSection = SchoolClass::whereHas('level', fn ($query) => $query->where('section_id', $level->section_id))->min('position');

        return $firstInSection !== null ? (int) $firstInSection : ((int) SchoolClass::max('position') + 1);
    }

    private function removeStepMember(SchoolClass $class): void
    {
        $position = $class->position;
        $class->update(['position' => null]);
        if (! SchoolClass::where('position', $position)->exists()) {
            SchoolClass::where('position', '>', $position)->decrement('position');
        }
    }

    private function assertValidParallelDepartments(Collection $classes): void
    {
        if ($classes->count() < 2) {
            return;
        }
        $departments = $classes->pluck('department_id');
        if ($departments->contains(null) && $departments->filter()->isNotEmpty()) {
            throw ValidationException::withMessages(['department_id' => 'A class without a department cannot share a step with department classes yet.']);
        }
        if ($departments->count() !== $departments->uniqueStrict()->count()) {
            throw ValidationException::withMessages(['department_id' => 'Classes on the same step must have different department tags.']);
        }
    }

    private function authorizeSection(User $actor, Section $section): void
    {
        if ($actor->can('manage_academics') && $actor->can('view_all_sections')) {
            return;
        }
        if (! $actor->can('manage_section_classes')) {
            throw new AuthorizationException('You do not have permission to manage classes.');
        }
        app(SectionAccess::class)->assertSection($actor, $section->id);
    }

    private function requiredSection(AcademicLevel $level): Section
    {
        $section = $level->section;
        if (! $section) {
            throw ValidationException::withMessages(['academic_level_id' => 'Assign this level to a section first.']);
        }

        return $section;
    }
}
