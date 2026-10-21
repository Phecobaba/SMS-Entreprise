<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Models\AttendanceAlert;
use App\Modules\Attendance\Models\AttendanceAlertSetting;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttendanceSessionNotHeld;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Payments\Services\ResultPaymentAccess;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function recordCountForArms(array $armIds): int
    {
        return $armIds === [] ? 0 : AttendanceRecord::whereIn('class_arm_id', $armIds)->count();
    }

    public function usageForArm(int $armId): array
    {
        return [
            'records' => AttendanceRecord::where('class_arm_id', $armId)->count(),
            'alerts' => AttendanceAlert::where('class_arm_id', $armId)->count(),
        ];
    }

    public function recordCountBetween(string $startDate, string $endDate): int
    {
        return AttendanceRecord::whereBetween('date', [$startDate, $endDate])->count();
    }

    public const SESSIONS = ['Morning', 'Afternoon'];

    public function dashboardToday(?int $armId = null, ?User $viewer = null): array
    {
        if ($viewer) {
            return collect($this->dashboardSummary($viewer, null, $armId)['sessions'])
                ->mapWithKeys(fn (array $session, string $name): array => [$name => $session['percentage']])
                ->all();
        }

        $date = now('Africa/Lagos')->toDateString();
        $query = ClassArm::with('schoolClass.level')->when($armId, fn ($query) => $query->whereKey($armId));
        if ($armId === null && $viewer) {
            app(SectionAccess::class)->scopeArms($query, $viewer);
        }
        $arms = $query->get();
        $counts = app(StudentDirectory::class)->countsByArmIds($arms->pluck('id')->all());
        $notHeld = AttendanceSessionNotHeld::whereDate('date', $date)->get()
            ->mapWithKeys(fn ($row) => [$row->section_id.'|'.$row->session => true]);

        return collect(self::SESSIONS)->mapWithKeys(function (string $session) use ($arms, $counts, $notHeld, $date): array {
            $armIds = $arms->filter(function (ClassArm $arm) use ($notHeld, $session): bool {
                $sectionId = $arm->schoolClass?->level?->section_id;

                return ! $notHeld->has($sectionId.'|'.$session);
            })->pluck('id');
            $expected = $armIds->sum(fn ($id) => (int) ($counts[$id] ?? 0));
            if ($expected === 0) {
                return [$session => null];
            }
            $records = AttendanceRecord::whereIn('class_arm_id', $armIds)->whereDate('date', $date)
                ->where(fn ($query) => $query->where('session', $session)->orWhereNull('session'));
            if ((clone $records)->distinct()->count('student_id') !== $expected) {
                return [$session => null];
            }

            return [$session => (int) round(100 * (clone $records)->whereIn('status', ['Present', 'Late'])
                ->distinct()->count('student_id') / $expected)];
        })->all();
    }

    public function dashboardSummary(User $viewer, ?int $sectionId = null, ?int $armId = null): array
    {
        $date = now('Africa/Lagos')->toDateString();
        $query = ClassArm::with('schoolClass.level.section')
            ->when($armId, fn ($builder) => $builder->whereKey($armId));
        if ($armId === null) {
            app(SectionAccess::class)->scopeArms($query, $viewer, $sectionId);
        }
        $arms = $query->get();
        $armIds = $arms->pluck('id');
        $counts = app(StudentDirectory::class)->countsByArmIds($armIds->all());
        $sectionNames = $arms->mapWithKeys(fn (ClassArm $arm): array => [
            $arm->schoolClass?->level?->section_id => $arm->schoolClass?->level?->section?->name,
        ])->filter();
        $notHeldRows = AttendanceSessionNotHeld::whereDate('date', $date)
            ->whereIn('section_id', $sectionNames->keys())->get();
        $notHeld = $notHeldRows->mapWithKeys(
            fn ($row): array => [$row->section_id.'|'.$row->session => true],
        );

        $sessions = collect(self::SESSIONS)->mapWithKeys(function (string $session) use ($arms, $counts, $notHeld, $date): array {
            $activeArms = $arms->filter(fn (ClassArm $arm): bool => (int) ($counts[$arm->id] ?? 0) > 0);
            $heldArms = $activeArms->reject(function (ClassArm $arm) use ($notHeld, $session): bool {
                return $notHeld->has($arm->schoolClass?->level?->section_id.'|'.$session);
            });
            $heldIds = $heldArms->pluck('id');
            $expected = $heldIds->sum(fn ($id): int => (int) ($counts[$id] ?? 0));
            $records = AttendanceRecord::whereIn('class_arm_id', $heldIds)->whereDate('date', $date)
                ->where(fn ($builder) => $builder->where('session', $session)->orWhereNull('session'));
            $markedByArm = (clone $records)->selectRaw('class_arm_id, count(distinct student_id) as marked_count')
                ->groupBy('class_arm_id')->pluck('marked_count', 'class_arm_id');
            $markedArms = $heldArms->filter(
                fn (ClassArm $arm): bool => (int) ($markedByArm[$arm->id] ?? 0) >= (int) ($counts[$arm->id] ?? 0),
            )->count();
            $complete = $expected > 0 && $markedArms === $heldArms->count();
            $counted = $complete ? (clone $records)->whereIn('status', ['Present', 'Late', 'Absent'])->distinct()->count('student_id') : 0;
            $present = $counted ? (clone $records)->whereIn('status', ['Present', 'Late'])->distinct()->count('student_id') : 0;

            return [$session => [
                'percentage' => $complete && $counted ? (int) round(100 * $present / $counted) : null,
                'not_held' => $activeArms->isNotEmpty() && $heldArms->isEmpty(),
                'marked_arms' => $markedArms,
                'total_arms' => $heldArms->count(),
            ]];
        })->all();

        $alertsQuery = AttendanceAlert::where('resolved', false)->whereDate('date', $date)
            ->whereIn('class_arm_id', $armIds);
        $alertsCount = (clone $alertsQuery)->count();
        $armLabels = $arms->keyBy('id');
        $alerts = $alertsQuery->orderBy('session')->orderBy('class_arm_id')->limit(4)->get()
            ->map(function (AttendanceAlert $alert) use ($armLabels): array {
                $arm = $armLabels->get($alert->class_arm_id);

                return [
                    'class' => trim(($arm?->schoolClass?->name ?? 'Class').' '.($arm?->name ?? '')),
                    'session' => $alert->session ?? 'Legacy whole day',
                    'date' => $alert->date,
                    'section' => $arm?->schoolClass?->level?->section?->name,
                    'url' => route('attendance.day', [
                        'arm' => $alert->class_arm_id,
                        'date' => $alert->date->toDateString(),
                        'session' => $alert->session ?? 'Morning',
                    ]),
                ];
            })->all();

        return [
            'sessions' => $sessions,
            'alerts_count' => $alertsCount,
            'alerts' => $alerts,
            'not_held' => $notHeldRows->map(fn ($row): array => [
                'section' => $sectionNames[$row->section_id] ?? 'Section',
                'session' => $row->session,
            ])->values()->all(),
        ];
    }

    public function dashboardRoster(int $armId, string $session): array
    {
        $this->assertSession($session);
        $roster = app(StudentDirectory::class)->dashboardRosterForArm($armId);
        $records = AttendanceRecord::where('class_arm_id', $armId)
            ->whereDate('date', now('Africa/Lagos')->toDateString())
            ->where(fn ($query) => $query->where('session', $session)->orWhereNull('session'))
            ->whereIn('student_id', $roster->pluck('id'))->get()->keyBy('student_id');

        return $roster->map(fn ($student) => [
            'name' => $student->user?->name ?? 'Student #'.$student->id,
            'status' => $records->get($student->id)?->status ?? 'Not marked',
        ])->all();
    }

    public function day(int $armId, string $date, string $session, User $actor): array
    {
        $this->assertSession($session);
        $arm = ClassArm::with('schoolClass.level')->findOrFail($armId);
        app(FormTeacherAssignments::class)->assertMayMark($arm, $actor);
        $roster = app(StudentDirectory::class)->rosterForArm($armId);
        $records = AttendanceRecord::where('class_arm_id', $armId)->whereDate('date', $date)
            ->where(fn ($query) => $query->where('session', $session)->orWhereNull('session'))
            ->get()->keyBy('student_id');
        $morningRecords = $session === 'Afternoon'
            ? AttendanceRecord::where('class_arm_id', $armId)->whereDate('date', $date)
                ->where(fn ($query) => $query->where('session', 'Morning')->orWhereNull('session'))
                ->get()->keyBy('student_id')
            : collect();
        $sectionId = $arm->schoolClass?->level?->section_id;
        $notHeld = $sectionId && AttendanceSessionNotHeld::where('section_id', $sectionId)
            ->whereDate('date', $date)->where('session', $session)->exists();

        return compact('arm', 'date', 'session', 'roster', 'records', 'morningRecords', 'notHeld');
    }

    public function markDay(int $armId, string $date, string $session, array $statuses, User $actor): void
    {
        $this->assertSession($session);
        $arm = ClassArm::with('schoolClass.level')->findOrFail($armId);
        app(FormTeacherAssignments::class)->assertMayMark($arm, $actor);
        $this->assertToday($date);
        $sectionId = $arm->schoolClass?->level?->section_id;
        if ($sectionId && AttendanceSessionNotHeld::where('section_id', $sectionId)->whereDate('date', $date)->where('session', $session)->exists()) {
            throw ValidationException::withMessages(['attendance' => 'This session is marked not held; no roll is needed.']);
        }
        $roster = app(StudentDirectory::class)->rosterForArm($armId);
        if ($roster->isEmpty()) {
            throw ValidationException::withMessages(['attendance' => 'This class arm has no enrolled students.']);
        }
        $studentIds = $roster->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $postedIds = array_map('intval', array_keys($statuses));
        sort($postedIds);
        if ($studentIds !== $postedIds) {
            throw ValidationException::withMessages(['attendance' => 'Mark every student in this class once. Refresh the roster and try again.']);
        }
        foreach ($statuses as $status) {
            if (! in_array($status, ['Present', 'Absent', 'Late', 'Excused'], true)) {
                throw ValidationException::withMessages(['attendance' => 'Choose a valid status for every student.']);
            }
        }

        DB::transaction(function () use ($armId, $date, $session, $statuses, $actor): void {
            $existing = AttendanceRecord::whereIn('student_id', array_keys($statuses))->whereDate('date', $date)
                ->where(fn ($query) => $query->where('session', $session)->orWhereNull('session'))
                ->lockForUpdate()->get();
            if ($existing->contains(fn (AttendanceRecord $record) => $record->session === null)) {
                throw ValidationException::withMessages(['attendance' => 'This date has legacy whole-day attendance and cannot be split into sessions.']);
            }
            if ($existing->contains(fn (AttendanceRecord $record) => $record->locked)) {
                throw ValidationException::withMessages(['attendance' => 'This session is locked and cannot be changed.']);
            }
            if ($existing->contains(fn (AttendanceRecord $record) => $record->class_arm_id !== $armId)) {
                throw ValidationException::withMessages(['attendance' => 'A student already has this session recorded in another class arm.']);
            }
            foreach ($statuses as $studentId => $status) {
                AttendanceRecord::updateOrCreate(
                    ['student_id' => (int) $studentId, 'date' => $date, 'session' => $session],
                    ['class_arm_id' => $armId, 'status' => $status, 'marked_by' => $actor->id],
                );
            }
            AttendanceAlert::where('class_arm_id', $armId)->whereDate('date', $date)
                ->where('session', $session)->update(['resolved' => true]);
        });
    }

    public function lockDay(int $armId, string $date, string $session, User $actor): void
    {
        $this->assertSession($session);
        Gate::forUser($actor)->authorize('manage_attendance');
        $this->assertToday($date);
        app(SectionAccess::class)->assertClassArm($actor, $armId);
        app(ClassArmDirectory::class)->assertExists($armId);
        $studentIds = app(StudentDirectory::class)->rosterForArm($armId)->pluck('id')->sort()->values()->all();
        $records = AttendanceRecord::where('class_arm_id', $armId)->whereDate('date', $date)
            ->where(fn ($query) => $query->where('session', $session)->orWhereNull('session'))->get();
        if ($studentIds === [] || $records->pluck('student_id')->sort()->values()->all() !== $studentIds) {
            throw ValidationException::withMessages(['attendance' => 'Mark every student before locking this session.']);
        }
        AttendanceRecord::whereKey($records->pluck('id'))->update(['locked' => true]);
    }

    public function generateMissingAlerts(string $date, string $session, ?CarbonImmutable $now = null): int
    {
        $this->assertSession($session);
        $day = CarbonImmutable::parse($date, 'Africa/Lagos');
        if ($day->isWeekend()) {
            return 0;
        }
        $now ??= CarbonImmutable::now('Africa/Lagos');
        $arms = ClassArm::with('schoolClass.level')->get();
        $counts = app(StudentDirectory::class)->countsByArmIds($arms->pluck('id')->all());
        $marked = AttendanceRecord::whereIn('class_arm_id', $arms->pluck('id'))->whereDate('date', $date)
            ->where(fn ($query) => $query->where('session', $session)->orWhereNull('session'))
            ->selectRaw('class_arm_id, count(distinct student_id) as marked_count')->groupBy('class_arm_id')
            ->pluck('marked_count', 'class_arm_id')->all();
        $settings = AttendanceAlertSetting::all()->keyBy('section_id');
        $notHeld = AttendanceSessionNotHeld::whereDate('date', $date)->where('session', $session)->pluck('section_id')->all();
        $created = 0;
        foreach ($arms as $arm) {
            $sectionId = $arm->schoolClass?->level?->section_id;
            if (! $sectionId) {
                continue;
            }
            $due = $session === 'Morning' ? ($settings[$sectionId]?->morning_due_at ?? '08:00') : ($settings[$sectionId]?->afternoon_due_at ?? '14:00');
            $dueAt = CarbonImmutable::parse($date.' '.substr($due, 0, 5), 'Africa/Lagos');
            $expected = (int) ($counts[$arm->id] ?? 0);
            $skip = in_array($sectionId, $notHeld, true) || $expected === 0 || $now->lt($dueAt);
            $complete = (int) ($marked[$arm->id] ?? 0) >= $expected;
            if ($skip || $complete) {
                AttendanceAlert::where('class_arm_id', $arm->id)->whereDate('date', $date)
                    ->where('session', $session)->update(['resolved' => true]);

                continue;
            }
            $alert = AttendanceAlert::firstOrCreate(
                ['class_arm_id' => $arm->id, 'date' => $date, 'session' => $session], ['resolved' => false]
            );
            if ($alert->wasRecentlyCreated) {
                $created++;
            } elseif ($alert->resolved) {
                $alert->update(['resolved' => false]);
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

        return $query->orderByDesc('date')->orderBy('session')->paginate(20)->withQueryString();
    }

    public function sectionSettings(int $sectionId, User $actor): array
    {
        $this->authorizeSectionManagement($sectionId, $actor);
        $setting = AttendanceAlertSetting::where('section_id', $sectionId)->first();

        return [
            'section' => Section::findOrFail($sectionId),
            'setting' => $setting,
            'morning_due_at' => substr($setting?->morning_due_at ?? '08:00', 0, 5),
            'afternoon_due_at' => substr($setting?->afternoon_due_at ?? '14:00', 0, 5),
            'notHeld' => AttendanceSessionNotHeld::with('marker')->where('section_id', $sectionId)->orderByDesc('date')->paginate(20),
        ];
    }

    public function updateSectionSettings(int $sectionId, string $morning, string $afternoon, User $actor): void
    {
        $this->authorizeSectionManagement($sectionId, $actor);
        if ($morning >= $afternoon) {
            throw ValidationException::withMessages(['afternoon_due_at' => 'The afternoon time must be later than the morning time.']);
        }
        DB::transaction(function () use ($sectionId, $morning, $afternoon, $actor): void {
            $setting = AttendanceAlertSetting::where('section_id', $sectionId)->lockForUpdate()->first();
            $old = ['morning' => substr($setting?->morning_due_at ?? '08:00', 0, 5), 'afternoon' => substr($setting?->afternoon_due_at ?? '14:00', 0, 5)];
            $setting = AttendanceAlertSetting::updateOrCreate(['section_id' => $sectionId], [
                'morning_due_at' => $morning, 'afternoon_due_at' => $afternoon, 'updated_by' => $actor->id,
            ]);
            app(AuditRecorder::class)->record($actor, 'attendance.alert_times_updated', $setting, [
                'section_id' => $sectionId, 'old' => $old, 'new' => ['morning' => $morning, 'afternoon' => $afternoon],
            ]);
        });
    }

    public function previewNotHeld(int $sectionId, string $date, string $session, User $actor): array
    {
        $sessions = $this->selectedSessions($session);
        $this->authorizeSectionManagement($sectionId, $actor);

        return $this->notHeldImpact($sectionId, $date, $session, $sessions);
    }

    public function previewNotHeldBatch(array $requests, User $actor): array
    {
        $normalized = collect($requests)->map(function (array $request): array {
            $this->assertSession($request['session']);

            return [
                'section_id' => (int) $request['section_id'],
                'date' => $request['date'],
                'session' => $request['session'],
            ];
        })->unique(fn (array $request) => $this->notHeldKey($request['section_id'], $request['date'], $request['session']))->values();

        foreach ($normalized->pluck('section_id')->unique() as $sectionId) {
            $this->authorizeSectionManagement((int) $sectionId, $actor);
        }

        return $this->notHeldImpacts($normalized->all());
    }

    public function markNotHeld(int $sectionId, string $date, string $session, User $actor): array
    {
        $sessions = $this->selectedSessions($session);
        $this->authorizeSectionManagement($sectionId, $actor);

        return DB::transaction(function () use ($sectionId, $date, $session, $sessions, $actor): array {
            foreach ($sessions as $selectedSession) {
                if (AttendanceSessionNotHeld::where('section_id', $sectionId)->whereDate('date', $date)->where('session', $selectedSession)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['session' => "{$selectedSession} on this date is already marked not held."]);
                }
            }

            $impact = $this->notHeldImpact($sectionId, $date, $session, $sessions);
            $armIds = ClassArm::whereHas('schoolClass.level', fn ($query) => $query->where('section_id', $sectionId))->pluck('id');

            foreach ($sessions as $selectedSession) {
                $entry = AttendanceSessionNotHeld::create([
                    'section_id' => $sectionId, 'date' => $date, 'session' => $selectedSession, 'marked_by' => $actor->id, 'created_at' => now(),
                ]);
                $cleared = AttendanceAlert::whereIn('class_arm_id', $armIds)->whereDate('date', $date)
                    ->where('session', $selectedSession)->where('resolved', false)->update(['resolved' => true]);
                $impact['sessions'][$selectedSession]['open_alerts'] = $cleared;
                app(AuditRecorder::class)->record($actor, 'attendance.session_not_held_added', $entry, [
                    'section_id' => $sectionId, 'date' => $date, 'session' => $selectedSession,
                ]);
            }

            $impact['open_alerts'] = collect($impact['sessions'])->sum('open_alerts');

            return $impact;
        });
    }

    public function removeNotHeld(int $entryId, User $actor): void
    {
        $entry = AttendanceSessionNotHeld::findOrFail($entryId);
        $this->authorizeSectionManagement($entry->section_id, $actor);
        if ($entry->date->lt(today('Africa/Lagos'))) {
            throw ValidationException::withMessages(['session' => 'Past not-held entries are kept as a record and cannot be removed.']);
        }
        DB::transaction(function () use ($entry, $actor): void {
            app(AuditRecorder::class)->record($actor, 'attendance.session_not_held_removed', $entry, [
                'section_id' => $entry->section_id, 'date' => $entry->date->toDateString(), 'session' => $entry->session,
            ]);
            $entry->delete();
        });
    }

    public function portalSummary(int $studentId, User $viewer): array
    {
        abort_unless(app(StudentDirectory::class)->isActive($studentId), 403);
        app(ResultPaymentAccess::class)->authorizeAttendance($studentId, $viewer);

        return $this->portalData($studentId);
    }

    public function portalRecords(int $studentId, User $viewer): array
    {
        abort_unless(in_array(app(StudentDirectory::class)->status($studentId), [Student::STATUS_ACTIVE, Student::STATUS_GRADUATED], true), 403);
        app(ResultPaymentAccess::class)->authorizeAttendance($studentId, $viewer);
        $data = $this->portalData($studentId);
        $page = LengthAwarePaginator::resolveCurrentPage('page');
        $data['rows'] = new LengthAwarePaginator(
            $data['days']->forPage($page, 20)->values(), $data['days']->count(), 20, $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );

        return $data;
    }

    private function portalData(int $studentId): array
    {
        $dates = AttendanceRecord::where('student_id', $studentId)->select('date')->distinct()->orderByDesc('date')->get()->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date))->filter(fn (CarbonImmutable $date) => ! $date->isWeekend())
            ->unique(fn (CarbonImmutable $date) => $date->toDateString())->values();
        $dateStrings = $dates->map->toDateString()->all();
        $records = AttendanceRecord::where('student_id', $studentId)->whereIn(DB::raw('DATE(date)'), $dateStrings)
            ->orderByDesc('date')->get()->groupBy(fn (AttendanceRecord $record) => $record->date->toDateString());
        $armSection = ClassArm::with('schoolClass.level')->whereIn('id', $records->flatten()->pluck('class_arm_id')->unique())
            ->get()->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->schoolClass?->level?->section_id]);
        $notHeld = AttendanceSessionNotHeld::whereIn('section_id', $armSection->filter()->unique())
            ->whereIn(DB::raw('DATE(date)'), $dateStrings)->get()
            ->mapWithKeys(fn ($row) => [$row->section_id.'|'.$row->date->toDateString().'|'.$row->session => true]);
        $days = $dates->map(function (CarbonImmutable $date) use ($records, $armSection, $notHeld): array {
            $dateKey = $date->toDateString();
            $dayRecords = $records->get($dateKey, collect());
            $legacy = $dayRecords->first(fn (AttendanceRecord $record) => $record->session === null);
            $sectionId = $armSection->get($dayRecords->first()?->class_arm_id);
            $sessions = collect(self::SESSIONS)->mapWithKeys(function (string $session) use ($dayRecords, $legacy, $sectionId, $dateKey, $notHeld): array {
                if ($sectionId && $notHeld->has($sectionId.'|'.$dateKey.'|'.$session)) {
                    return [$session => 'Not held'];
                }
                $status = $dayRecords->firstWhere('session', $session)?->status ?? $legacy?->status ?? 'Not marked';

                return [$session => $status];
            })->all();

            return ['date' => $date, 'sessions' => $sessions];
        })->filter(fn (array $day) => collect($day['sessions'])->contains(fn (string $status) => $status !== 'Not held'))
            ->take(30)->values();
        $present = 0;
        $counted = 0;
        $absent = 0;
        foreach ($days as $day) {
            foreach ($day['sessions'] as $status) {
                if (in_array($status, ['Present', 'Late', 'Absent'], true)) {
                    $counted++;
                    if ($status === 'Absent') {
                        $absent++;
                    } else {
                        $present++;
                    }
                }
            }
        }

        return [
            'percentage' => $counted ? (int) round(100 * $present / $counted) : null,
            'present_sessions' => $present,
            'counted_sessions' => $counted,
            'absent_sessions' => $absent,
            'marked_days' => $days->count(),
            'days' => $days,
            'strip' => $days,
            'recent' => $days->take(5),
            'today' => $days->first(fn (array $day) => $day['date']->isToday()),
        ];
    }

    private function authorizeSectionManagement(int $sectionId, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_attendance');
        app(SectionAccess::class)->assertSection($actor, $sectionId);
    }

    private function selectedSessions(string $session): array
    {
        if ($session === 'Both') {
            return self::SESSIONS;
        }

        $this->assertSession($session);

        return [$session];
    }

    private function notHeldImpact(int $sectionId, string $date, string $selection, array $sessions): array
    {
        $requests = collect($sessions)->map(fn (string $session): array => [
            'section_id' => $sectionId,
            'date' => $date,
            'session' => $session,
        ])->all();
        $impacts = $this->notHeldImpacts($requests);
        $bySession = collect($sessions)->mapWithKeys(function (string $session) use ($impacts, $sectionId, $date): array {
            $key = $this->notHeldKey($sectionId, $date, $session);

            return [$session => $impacts[$key]];
        })->all();

        return [
            'section_id' => $sectionId,
            'date' => $date,
            'selection' => $selection,
            'open_alerts' => collect($bySession)->sum('open_alerts'),
            'sessions' => $bySession,
        ];
    }

    private function notHeldImpacts(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $sectionIds = collect($requests)->pluck('section_id')->unique()->values();
        $dates = collect($requests)->pluck('date')->unique()->values();
        $sessions = collect($requests)->pluck('session')->unique()->values();
        $arms = ClassArm::with('schoolClass.level')
            ->whereHas('schoolClass.level', fn ($query) => $query->whereIn('section_id', $sectionIds))
            ->get();
        $armSections = $arms->mapWithKeys(fn (ClassArm $arm): array => [
            $arm->id => $arm->schoolClass?->level?->section_id,
        ]);
        $armIds = $armSections->keys();
        $alerts = AttendanceAlert::whereIn('class_arm_id', $armIds)->whereIn(DB::raw('DATE(date)'), $dates)
            ->whereIn('session', $sessions)->where('resolved', false)->get(['class_arm_id', 'date', 'session']);
        $records = AttendanceRecord::whereIn('class_arm_id', $armIds)->whereIn(DB::raw('DATE(date)'), $dates)
            ->where(fn ($query) => $query->whereIn('session', $sessions)->orWhereNull('session'))
            ->get(['student_id', 'class_arm_id', 'date', 'session']);

        return collect($requests)->mapWithKeys(function (array $request) use ($alerts, $records, $armSections): array {
            $matchesSectionAndDate = fn ($row): bool => $armSections->get($row->class_arm_id) === $request['section_id']
                && $row->date->toDateString() === $request['date'];
            $key = $this->notHeldKey($request['section_id'], $request['date'], $request['session']);

            return [$key => [
                'section_id' => $request['section_id'],
                'date' => $request['date'],
                'session' => $request['session'],
                'open_alerts' => $alerts->filter($matchesSectionAndDate)->where('session', $request['session'])->count(),
                'marked_students' => $records->filter($matchesSectionAndDate)
                    ->filter(fn (AttendanceRecord $record): bool => $record->session === null || $record->session === $request['session'])
                    ->pluck('student_id')->unique()->count(),
            ]];
        })->all();
    }

    private function notHeldKey(int $sectionId, string $date, string $session): string
    {
        return $sectionId.'|'.$date.'|'.$session;
    }

    private function assertSession(string $session): void
    {
        if (! in_array($session, self::SESSIONS, true)) {
            throw ValidationException::withMessages(['session' => 'Choose Morning or Afternoon.']);
        }
    }

    private function assertToday(string $date): void
    {
        if ($date !== CarbonImmutable::now('Africa/Lagos')->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Attendance can only be marked or changed on the same school day.']);
        }
    }
}
