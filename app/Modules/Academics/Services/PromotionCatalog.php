<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PromotionCatalog
{
    public function sectionsFor(User $actor): Collection
    {
        $query = Section::orderBy('name');
        if (! $actor->can('view_all_sections')) {
            $query->whereIn('id', app(SectionAccess::class)->sectionIds($actor));
        }

        return $query->get();
    }

    public function assertSection(User $actor, int $sectionId): void
    {
        if ($actor->can('view_all_sections')) {
            Section::findOrFail($sectionId);

            return;
        }

        app(SectionAccess::class)->assertSection($actor, $sectionId);
    }

    public function assertClass(User $actor, int $classId): SchoolClass
    {
        $class = SchoolClass::with(['level.section', 'department', 'arms'])->findOrFail($classId);
        $section = $class->level?->section;
        if (! $section) {
            throw ValidationException::withMessages(['class' => 'This class must belong to a section before it can be promoted.']);
        }
        $this->assertSection($actor, $section->id);

        return $class;
    }

    public function classesForSection(User $actor, int $sectionId): Collection
    {
        $this->assertSection($actor, $sectionId);

        return SchoolClass::with(['level.section', 'department', 'arms'])
            ->whereHas('level', fn ($query) => $query->where('section_id', $sectionId))
            ->whereNotNull('position')->orderBy('position')->orderBy('name')->get();
    }

    public function sourceArmIds(SchoolClass $class, ?int $armId = null): array
    {
        $ids = $class->arms->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($armId !== null && ! in_array($armId, $ids, true)) {
            throw ValidationException::withMessages(['arm_id' => 'Choose an arm in the source class.']);
        }

        return $armId ? [$armId] : $ids;
    }

    public function nextStep(SchoolClass $source): Collection
    {
        $nextPosition = SchoolClass::whereHas('level', fn ($query) => $query->where('section_id', $source->level->section_id))
            ->where('position', '>', $source->position)->min('position');
        if ($nextPosition === null) {
            return collect();
        }

        return SchoolClass::with(['level.section', 'department', 'arms'])
            ->whereHas('level', fn ($query) => $query->where('section_id', $source->level->section_id))
            ->where('position', $nextPosition)->orderBy('name')->get();
    }

    public function finalStatus(SchoolClass $source): string
    {
        if ($this->nextStep($source)->isNotEmpty()) {
            throw ValidationException::withMessages(['class' => 'This is not the final class in its section.']);
        }

        $higherOtherSection = SchoolClass::where('position', '>', $source->position)
            ->whereHas('level', fn ($query) => $query->where('section_id', '!=', $source->level->section_id))
            ->exists();

        return $higherOtherSection ? 'Awaiting admission' : 'Graduated';
    }

    public function suggestedClass(SchoolClass $source, Collection $nextStep): ?SchoolClass
    {
        if ($nextStep->isEmpty()) {
            return null;
        }
        if ($source->department_id !== null) {
            return $nextStep->firstWhere('department_id', $source->department_id);
        }

        return $nextStep->count() === 1 ? $nextStep->first() : null;
    }

    public function suggestedArm(ClassArm $sourceArm, ?SchoolClass $destination): ?ClassArm
    {
        if (! $destination) {
            return null;
        }

        return $destination->arms->first(fn (ClassArm $arm) => strcasecmp($arm->name, $sourceArm->name) === 0)
            ?? $destination->arms->sort(fn (ClassArm $a, ClassArm $b) => strnatcasecmp($a->name, $b->name))->first();
    }

    public function assertDestination(User $actor, SchoolClass $source, int $classId, int $armId, bool $single = false): ClassArm
    {
        $destination = $this->assertClass($actor, $classId);
        if (! $single && ! $this->nextStep($source)->contains('id', $destination->id)) {
            throw ValidationException::withMessages(['destination' => 'Choose the next class in the saved order, or leave the student in the source class.']);
        }
        $arm = ClassArm::with('schoolClass')->where('class_id', $destination->id)->find($armId);
        if (! $arm) {
            throw ValidationException::withMessages(['destination' => 'Choose an arm that belongs to the destination class.']);
        }

        return $arm;
    }

    public function waitingForArrangement(): Collection
    {
        return Section::whereNull('classes_arranged_at')->orderBy('name')->get();
    }

    public function assertEverySectionArranged(): void
    {
        $waiting = $this->waitingForArrangement();
        if ($waiting->isNotEmpty()) {
            throw ValidationException::withMessages([
                'promotion' => 'Arrange every section before promoting students. Waiting for '.$waiting->pluck('name')->join(', ').'.',
            ]);
        }
    }

    public function assertMayPromote(User $actor): void
    {
        if (! $actor->can('manage_promotions')) {
            throw new AuthorizationException('You do not have permission to promote students.');
        }
    }
}
