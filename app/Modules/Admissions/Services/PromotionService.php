<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Services\PromotionCatalog;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function preview(User $actor, int $sourceClassId, ?int $sourceArmId, array $placements): array
    {
        $catalog = app(PromotionCatalog::class);
        $catalog->assertMayPromote($actor);
        $catalog->assertEverySectionArranged();
        $source = $catalog->assertClass($actor, $sourceClassId);

        return $this->buildPlan($actor, $source, $sourceArmId, $placements);
    }

    public function apply(User $actor, int $sourceClassId, ?int $sourceArmId, array $placements): array
    {
        $catalog = app(PromotionCatalog::class);
        $catalog->assertMayPromote($actor);
        $catalog->assertEverySectionArranged();
        $source = $catalog->assertClass($actor, $sourceClassId);

        return DB::transaction(function () use ($actor, $source, $sourceArmId, $placements): array {
            Student::whereIn('id', array_map('intval', array_keys($placements)))->lockForUpdate()->get();
            $plan = $this->buildPlan($actor, $source, $sourceArmId, $placements);

            foreach ($plan['rows'] as $row) {
                if ($row['action'] === 'stay') {
                    continue;
                }
                $student = Student::whereKey($row['student']->id)->lockForUpdate()->first();
                if (! $student || $student->status !== Student::STATUS_ACTIVE
                    || ! in_array($student->class_arm_id, $plan['source_arm_ids'], true)) {
                    $plan['skipped']++;
                    $plan['moved']--;

                    continue;
                }

                $before = ['class_arm_id' => $student->class_arm_id, 'status' => $student->status];
                if ($row['status'] === Student::STATUS_AWAITING) {
                    $student->update([
                        'status' => Student::STATUS_AWAITING,
                        'awaiting_from_class_arm_id' => $student->class_arm_id,
                        'awaiting_admission_at' => now(),
                    ]);
                } elseif ($row['status'] === Student::STATUS_GRADUATED) {
                    $student->update([
                        'status' => Student::STATUS_GRADUATED,
                        'awaiting_from_class_arm_id' => null,
                        'awaiting_admission_at' => null,
                    ]);
                } else {
                    $student->update([
                        'class_arm_id' => $row['destination_arm_id'],
                        'status' => Student::STATUS_ACTIVE,
                        'awaiting_from_class_arm_id' => null,
                        'awaiting_admission_at' => null,
                    ]);
                }

                app(AuditRecorder::class)->record($actor, 'student_promoted', $student, [
                    'from' => $before,
                    'to' => ['class_arm_id' => $student->class_arm_id, 'status' => $student->status],
                    'source_class_id' => $source->id,
                ]);
            }

            return $plan;
        });
    }

    public function moveOne(User $actor, int $studentId, int $destinationClassId, int $destinationArmId): Student
    {
        $catalog = app(PromotionCatalog::class);
        $catalog->assertMayPromote($actor);
        $catalog->assertEverySectionArranged();

        return DB::transaction(function () use ($actor, $studentId, $destinationClassId, $destinationArmId, $catalog): Student {
            $student = Student::active()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $student->loadMissing('classArm.schoolClass.level.section');
            $source = $catalog->assertClass($actor, $student->classArm->class_id);
            $arm = $catalog->assertDestination($actor, $source, $destinationClassId, $destinationArmId, true);
            if ($student->class_arm_id === $arm->id) {
                return $student;
            }

            $from = $student->class_arm_id;
            $student->update([
                'class_arm_id' => $arm->id,
                'status' => Student::STATUS_ACTIVE,
                'awaiting_from_class_arm_id' => null,
                'awaiting_admission_at' => null,
            ]);
            app(AuditRecorder::class)->record($actor, 'student_moved', $student, [
                'from' => ['class_arm_id' => $from, 'status' => Student::STATUS_ACTIVE],
                'to' => ['class_arm_id' => $arm->id, 'status' => Student::STATUS_ACTIVE],
            ]);

            return $student->refresh();
        });
    }

    public function suggestion(Student $student, SchoolClass $source): array
    {
        $catalog = app(PromotionCatalog::class);
        $next = $catalog->nextStep($source);
        if ($next->isEmpty()) {
            return ['final' => $catalog->finalStatus($source), 'class' => null, 'arm' => null];
        }
        $destination = $catalog->suggestedClass($source, $next);
        $arm = $catalog->suggestedArm($student->classArm, $destination);

        return ['final' => null, 'class' => $destination, 'arm' => $arm];
    }

    private function buildPlan(User $actor, SchoolClass $source, ?int $sourceArmId, array $placements): array
    {
        if ($placements === []) {
            throw ValidationException::withMessages(['students' => 'Select at least one student to review.']);
        }
        $catalog = app(PromotionCatalog::class);
        $sourceArmIds = $catalog->sourceArmIds($source, $sourceArmId);
        $requestedIds = collect(array_keys($placements))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $students = Student::active()->with(['user', 'classArm.schoolClass'])
            ->whereIn('id', $requestedIds)->whereIn('class_arm_id', $sourceArmIds)->get()->keyBy('id');
        $nextStep = $catalog->nextStep($source);
        $finalStatus = $nextStep->isEmpty() ? $catalog->finalStatus($source) : null;
        $rows = collect();
        $errors = [];

        foreach ($requestedIds as $studentId) {
            $student = $students->get($studentId);
            if (! $student) {
                continue;
            }
            if ($finalStatus) {
                $rows->push([
                    'student' => $student, 'action' => 'move', 'status' => $finalStatus,
                    'destination_class_id' => null, 'destination_arm_id' => null,
                    'destination' => $finalStatus,
                ]);

                continue;
            }

            $choice = $placements[$studentId] ?? $placements[(string) $studentId] ?? [];
            if ((bool) ($choice['stay'] ?? false) || (int) ($choice['class_id'] ?? 0) === $source->id) {
                $rows->push([
                    'student' => $student, 'action' => 'stay', 'status' => Student::STATUS_ACTIVE,
                    'destination_class_id' => $source->id, 'destination_arm_id' => $student->class_arm_id,
                    'destination' => $source->name.' · '.$student->classArm->name.' (stay)',
                ]);

                continue;
            }

            $suggestedClass = $catalog->suggestedClass($source, $nextStep);
            $classId = (int) ($choice['class_id'] ?? $suggestedClass?->id ?? 0);
            $destination = $nextStep->firstWhere('id', $classId);
            $suggestedArm = $catalog->suggestedArm($student->classArm, $destination);
            $armId = (int) ($choice['arm_id'] ?? $suggestedArm?->id ?? 0);
            if (! $classId || ! $armId) {
                $errors['destinations.'.$studentId] = 'Choose a class and arm for '.$student->user?->name.'.';

                continue;
            }
            try {
                $arm = $catalog->assertDestination($actor, $source, $classId, $armId);
            } catch (ValidationException $exception) {
                $errors['destinations.'.$studentId] = collect($exception->errors())->flatten()->first();

                continue;
            }
            $rows->push([
                'student' => $student, 'action' => 'move', 'status' => Student::STATUS_ACTIVE,
                'destination_class_id' => $classId, 'destination_arm_id' => $arm->id,
                'destination' => $arm->schoolClass?->name.' · '.$arm->name,
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'source' => $source,
            'source_arm_ids' => $sourceArmIds,
            'rows' => $rows,
            'moved' => $rows->where('action', 'move')->count(),
            'stayed' => $rows->where('action', 'stay')->count(),
            'skipped' => $requestedIds->count() - $students->count(),
            'final_status' => $finalStatus,
        ];
    }
}
