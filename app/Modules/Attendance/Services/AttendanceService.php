<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Models\AttendanceAlert;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Services\ResultPaymentAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function dashboardToday(?int $armId = null, ?User $viewer = null): ?int
    {
        $query = AttendanceRecord::whereDate('date', now('Africa/Lagos')->toDateString());
        if ($armId !== null) {
            $query->where('class_arm_id', $armId);
        } elseif ($viewer) {
            $armIds = app(SectionAccess::class)->visibleArmIds($viewer);
            if ($armIds !== null) {
                $query->whereIn('class_arm_id', $armIds);
            }
        }
        $total = (clone $query)->count();
        $expected = $armId === null
            ? app(StudentDirectory::class)->count($viewer)
            : app(StudentDirectory::class)->countForArm($armId);
        if ($expected === 0 || $total !== $expected) {
            return null;
        }

        return (int) round(100 * (clone $query)->whereIn('status', ['Present', 'Late'])->count() / $total);
    }

    public function dashboardRoster(int $armId): array
    {
        $roster = app(StudentDirectory::class)->dashboardRosterForArm($armId);
        $records = AttendanceRecord::where('class_arm_id', $armId)
            ->whereDate('date', now('Africa/Lagos')->toDateString())
            ->whereIn('student_id', $roster->pluck('id'))
            ->get()->keyBy('student_id');

        return $roster->map(fn ($student) => [
            'name' => $student->user?->name ?? 'Student #'.$student->id,
            'status' => $records->get($student->id)?->status ?? 'Not marked',
        ])->all();
    }

    public function day(int $armId, string $date, User $actor): array
    {
        $arm = ClassArm::findOrFail($armId);
        app(FormTeacherAssignments::class)->assertMayMark($arm, $actor);
        $roster = app(StudentDirectory::class)->rosterForArm($armId);
        $records = AttendanceRecord::where('class_arm_id', $armId)->whereDate('date', $date)
            ->get()->keyBy('student_id');

        return ['arm' => $arm, 'date' => $date, 'roster' => $roster, 'records' => $records];
    }

    public function markDay(int $armId, string $date, array $statuses, User $actor): void
    {
        $arm = ClassArm::findOrFail($armId);
        app(FormTeacherAssignments::class)->assertMayMark($arm, $actor);
        $this->assertToday($date);
        $roster = app(StudentDirectory::class)->rosterForArm($armId);
        if ($roster->isEmpty()) {
            throw ValidationException::withMessages(['attendance' => 'This class arm has no enrolled students.']);
        }
        $studentIds = $roster->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $postedIds = array_keys($statuses);
        $postedIds = array_map('intval', $postedIds);
        sort($postedIds);
        if ($studentIds !== $postedIds) {
            throw ValidationException::withMessages(['attendance' => 'Mark every student in this class once. Refresh the roster and try again.']);
        }
        foreach ($statuses as $status) {
            if (! in_array($status, ['Present', 'Absent', 'Late', 'Excused'], true)) {
                throw ValidationException::withMessages(['attendance' => 'Choose a valid status for every student.']);
            }
        }

        DB::transaction(function () use ($armId, $date, $statuses, $actor): void {
            $existing = AttendanceRecord::whereIn('student_id', array_keys($statuses))->whereDate('date', $date)
                ->lockForUpdate()->get()->keyBy('student_id');
            if ($existing->contains(fn (AttendanceRecord $record) => $record->locked)) {
                throw ValidationException::withMessages(['attendance' => 'This day’s attendance is locked and cannot be changed.']);
            }
            if ($existing->contains(fn (AttendanceRecord $record) => $record->class_arm_id !== $armId)) {
                throw ValidationException::withMessages(['attendance' => 'A student already has attendance recorded in another class arm today.']);
            }
            foreach ($statuses as $studentId => $status) {
                AttendanceRecord::updateOrCreate(
                    ['student_id' => (int) $studentId, 'date' => $date],
                    ['class_arm_id' => $armId, 'status' => $status, 'marked_by' => $actor->id],
                );
            }
            AttendanceAlert::where('class_arm_id', $armId)->whereDate('date', $date)->update(['resolved' => true]);
        });
    }

    public function lockDay(int $armId, string $date, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_attendance');
        $this->assertToday($date);
        app(SectionAccess::class)->assertClassArm($actor, $armId);
        app(ClassArmDirectory::class)->assertExists($armId);
        $studentIds = app(StudentDirectory::class)->rosterForArm($armId)->pluck('id')->sort()->values()->all();
        $recordedIds = AttendanceRecord::where('class_arm_id', $armId)->whereDate('date', $date)->pluck('student_id')->sort()->values()->all();
        if ($studentIds === [] || $recordedIds !== $studentIds) {
            throw ValidationException::withMessages(['attendance' => 'Mark every student before locking this day.']);
        }
        AttendanceRecord::where('class_arm_id', $armId)->whereDate('date', $date)->update(['locked' => true]);
    }

    public function generateMissingAlerts(string $date): int
    {
        $day = CarbonImmutable::parse($date, 'Africa/Lagos');
        if ($day->isWeekend()) {
            return 0;
        }
        $arms = app(ClassArmDirectory::class)->all();
        $counts = app(StudentDirectory::class)->countsByArmIds($arms->pluck('id')->all());
        $marked = AttendanceRecord::whereIn('class_arm_id', $arms->pluck('id'))->whereDate('date', $date)
            ->selectRaw('class_arm_id, count(*) as marked_count')->groupBy('class_arm_id')
            ->pluck('marked_count', 'class_arm_id')->all();
        $created = 0;
        foreach ($arms as $arm) {
            $expected = (int) ($counts[$arm->id] ?? 0);
            if ($expected === 0) {
                continue;
            }
            if ((int) ($marked[$arm->id] ?? 0) < $expected) {
                $alert = AttendanceAlert::firstOrCreate(['class_arm_id' => $arm->id, 'date' => $date], ['resolved' => false]);
                if ($alert->wasRecentlyCreated) {
                    $created++;
                } elseif ($alert->resolved) {
                    $alert->update(['resolved' => false]);
                }
            } else {
                AttendanceAlert::where('class_arm_id', $arm->id)->whereDate('date', $date)->update(['resolved' => true]);
            }
        }

        return $created;
    }

    public function alerts(User $viewer)
    {
        $query = AttendanceAlert::where('resolved', false);
        $armIds = app(SectionAccess::class)->visibleArmIds($viewer);
        if ($armIds !== null) {
            $query->whereIn('class_arm_id', $armIds);
        }

        return $query->orderByDesc('date')->paginate(20);
    }

    public function portalSummary(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeAttendance($studentId, $viewer);
        $records = AttendanceRecord::where('student_id', $studentId)->latest('date')->limit(30)->get();
        $marked = $records->where('status', '!=', 'Excused')->count();
        $present = $records->whereIn('status', ['Present', 'Late'])->count();

        return [
            'percentage' => $marked ? (int) round(100 * $present / $marked) : null,
            'marked_days' => $records->count(),
            'recent' => $records->take(5),
        ];
    }

    public function portalRecords(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeAttendance($studentId, $viewer);
        $strip = AttendanceRecord::where('student_id', $studentId)->latest('date')->limit(30)->get();
        $counted = $strip->where('status', '!=', 'Excused')->count();

        return [
            'strip' => $strip,
            'percentage' => $counted ? (int) round(100 * $strip->whereIn('status', ['Present', 'Late'])->count() / $counted) : null,
            'marked_days' => $strip->count(),
            'rows' => AttendanceRecord::where('student_id', $studentId)->latest('date')->paginate(20)->withQueryString(),
        ];
    }

    private function assertToday(string $date): void
    {
        if ($date !== CarbonImmutable::now('Africa/Lagos')->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Attendance can only be marked or changed on the same school day.']);
        }
    }
}
