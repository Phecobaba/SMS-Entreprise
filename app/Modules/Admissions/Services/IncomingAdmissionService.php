<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\PromotionCatalog;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Payments\Services\FeeInvoicingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class IncomingAdmissionService
{
    public function sectionsFor(User $actor): Collection
    {
        app(PromotionCatalog::class)->assertMayPromote($actor);

        return app(PromotionCatalog::class)->sectionsFor($actor)
            ->filter(fn (Section $section) => $this->sourcePosition($section) !== null)
            ->values();
    }

    public function page(User $actor, int $sectionId): array
    {
        $section = $this->assertSection($actor, $sectionId);
        $classes = $this->destinationClasses($section);
        $students = $this->waitingQuery($section)
            ->with(['user', 'awaitingFromClassArm.schoolClass.department', 'awaitingFromClassArm.schoolClass.level.section'])
            ->orderBy('awaiting_admission_at')->orderBy('id')->paginate(20)->withQueryString();
        $suggestions = $students->getCollection()->mapWithKeys(fn (Student $student) => [
            $student->id => $this->suggestion($student, $classes),
        ]);

        return [
            'section' => $section,
            'classes' => $classes,
            'students' => $students,
            'suggestions' => $suggestions,
            'hasRegistrationNumber' => Schema::hasColumn('students', 'registration_number'),
        ];
    }

    public function previewAccept(User $actor, int $sectionId, array $placements): array
    {
        $section = $this->assertSection($actor, $sectionId);

        return $this->acceptPlan($section, $placements);
    }

    public function accept(User $actor, int $sectionId, array $placements): array
    {
        $section = $this->assertSection($actor, $sectionId);

        return DB::transaction(function () use ($actor, $section, $placements): array {
            Student::whereIn('id', array_map('intval', array_keys($placements)))->lockForUpdate()->get();
            $plan = $this->acceptPlan($section, $placements, true);

            foreach ($plan['rows'] as $row) {
                if ($row['unchanged']) {
                    continue;
                }
                /** @var Student $student */
                $student = $row['student'];
                $from = [
                    'status' => $student->status,
                    'class_arm_id' => $student->class_arm_id,
                    'awaiting_from_class_arm_id' => $student->awaiting_from_class_arm_id,
                    'awaiting_admission_at' => $student->awaiting_admission_at?->toIso8601String(),
                ];
                $student->update([
                    'status' => Student::STATUS_ACTIVE,
                    'class_arm_id' => $row['arm']->id,
                    'awaiting_from_class_arm_id' => null,
                    'awaiting_admission_at' => null,
                ]);
                app(AuditRecorder::class)->record($actor, 'student_incoming_accepted', $student, [
                    'from' => $from,
                    'to' => ['status' => Student::STATUS_ACTIVE, 'class_arm_id' => $row['arm']->id],
                    'destination_section_id' => $section->id,
                ]);
                app(FeeInvoicingService::class)->automaticallyInvoice($student, $actor);
            }

            return $plan;
        });
    }

    public function previewNotContinuing(User $actor, int $sectionId, array $studentIds, bool $allRemaining): array
    {
        $section = $this->assertSection($actor, $sectionId);

        return $this->notContinuingPlan($section, $studentIds, $allRemaining);
    }

    public function markNotContinuing(User $actor, int $sectionId, array $studentIds, bool $allRemaining): array
    {
        $section = $this->assertSection($actor, $sectionId);

        return DB::transaction(function () use ($actor, $section, $studentIds, $allRemaining): array {
            $plan = $this->notContinuingPlan($section, $studentIds, $allRemaining, true);
            foreach ($plan['rows'] as $student) {
                $from = [
                    'status' => $student->status,
                    'class_arm_id' => $student->class_arm_id,
                    'awaiting_from_class_arm_id' => $student->awaiting_from_class_arm_id,
                    'awaiting_admission_at' => $student->awaiting_admission_at?->toIso8601String(),
                ];
                $student->update([
                    'status' => Student::STATUS_GRADUATED,
                    'awaiting_from_class_arm_id' => null,
                    'awaiting_admission_at' => null,
                ]);
                app(AuditRecorder::class)->record($actor, 'student_marked_not_continuing', $student, [
                    'from' => $from,
                    'to' => ['status' => Student::STATUS_GRADUATED, 'class_arm_id' => $student->class_arm_id],
                    'destination_section_id' => $section->id,
                ]);
            }

            return $plan;
        });
    }

    private function assertSection(User $actor, int $sectionId): Section
    {
        app(PromotionCatalog::class)->assertMayPromote($actor);
        app(PromotionCatalog::class)->assertSection($actor, $sectionId);
        $section = $this->sectionsFor($actor)->firstWhere('id', $sectionId);
        if (! $section) {
            throw new AuthorizationException('This section does not receive incoming students from another section.');
        }

        return $section;
    }

    private function sourcePosition(Section $section): ?int
    {
        $firstPosition = SchoolClass::whereHas('level', fn (Builder $query) => $query->where('section_id', $section->id))->min('position');
        if ($firstPosition === null) {
            return null;
        }

        return SchoolClass::where('position', '<', $firstPosition)
            ->whereHas('level', fn (Builder $query) => $query->where('section_id', '!=', $section->id))
            ->max('position');
    }

    private function destinationClasses(Section $section): Collection
    {
        $position = SchoolClass::whereHas('level', fn (Builder $query) => $query->where('section_id', $section->id))->min('position');

        return SchoolClass::with(['level.section', 'department', 'arms'])
            ->whereHas('level', fn (Builder $query) => $query->where('section_id', $section->id))
            ->where('position', $position)->orderBy('name')->get();
    }

    private function waitingQuery(Section $section): Builder
    {
        $sourcePosition = $this->sourcePosition($section);

        return Student::query()->where('status', Student::STATUS_AWAITING)
            ->whereHas('awaitingFromClassArm.schoolClass', fn (Builder $query) => $query->where('position', $sourcePosition)
                ->whereHas('level', fn (Builder $level) => $level->where('section_id', '!=', $section->id)));
    }

    private function suggestion(Student $student, Collection $classes): array
    {
        $source = $student->awaitingFromClassArm?->schoolClass;
        $matching = $source?->department_id
            ? $classes->where('department_id', $source->department_id)->values()
            : $classes;
        $destination = $matching->count() === 1 ? $matching->first() : null;
        $arm = $destination
            ? ($destination->arms->first(fn (ClassArm $arm) => strcasecmp($arm->name, $student->awaitingFromClassArm?->name ?? '') === 0)
                ?? $destination->arms->sort(fn (ClassArm $a, ClassArm $b) => strnatcasecmp($a->name, $b->name))->first())
            : null;

        return ['class' => $destination, 'arm' => $arm];
    }

    private function acceptPlan(Section $section, array $placements, bool $allowIdempotent = false): array
    {
        if ($placements === []) {
            throw ValidationException::withMessages(['students' => 'Select at least one pupil to review.']);
        }
        $classes = $this->destinationClasses($section);
        $classIds = $classes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ids = collect(array_keys($placements))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $students = Student::with(['user', 'classArm.schoolClass', 'awaitingFromClassArm.schoolClass'])
            ->whereIn('id', $ids)->get()->keyBy('id');
        $rows = collect();
        $errors = [];

        foreach ($ids as $id) {
            $student = $students->get($id);
            $choice = $placements[$id] ?? $placements[(string) $id] ?? [];
            $classId = (int) ($choice['class_id'] ?? 0);
            $armId = (int) ($choice['arm_id'] ?? 0);
            $arm = ClassArm::with('schoolClass')->where('class_id', $classId)->find($armId);
            if (! in_array($classId, $classIds, true) || ! $arm) {
                $errors['destinations.'.$id] = 'Choose a class and arm inside '.$section->name.'.';

                continue;
            }
            $unchanged = $allowIdempotent && $student?->status === Student::STATUS_ACTIVE && $student->class_arm_id === $arm->id;
            if (! $unchanged && (! $student || $student->status !== Student::STATUS_AWAITING || ! $this->waitingQuery($section)->whereKey($id)->exists())) {
                $errors['students.'.$id] = 'This pupil is not waiting for admission into '.$section->name.'.';

                continue;
            }
            $rows->push(['student' => $student, 'arm' => $arm, 'unchanged' => $unchanged]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'section' => $section,
            'rows' => $rows,
            'accepted' => $rows->where('unchanged', false)->count(),
            'unchanged' => $rows->where('unchanged', true)->count(),
            'remaining' => max(0, $this->waitingQuery($section)->count() - $rows->where('unchanged', false)->count()),
        ];
    }

    private function notContinuingPlan(Section $section, array $studentIds, bool $allRemaining, bool $lock = false): array
    {
        $query = $this->waitingQuery($section)->with(['user', 'awaitingFromClassArm.schoolClass']);
        if ($allRemaining) {
            $students = ($lock ? $query->lockForUpdate() : $query)->orderBy('id')->get();
        } else {
            $ids = collect($studentIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['students' => 'Select at least one pupil to review.']);
            }
            if ($lock) {
                Student::whereIn('id', $ids)->lockForUpdate()->get();
            }
            $students = $query->whereIn('id', $ids)->get();
            if ($students->count() !== $ids->count()) {
                throw ValidationException::withMessages(['students' => 'Every selected pupil must still be waiting for this section.']);
            }
        }

        return [
            'section' => $section,
            'rows' => $students,
            'graduated' => $students->count(),
            'remaining' => max(0, $this->waitingQuery($section)->count() - $students->count()),
            'all_remaining' => $allRemaining,
        ];
    }
}
