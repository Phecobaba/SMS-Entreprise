<?php

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\FormTeacherAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\Services\SchoolUserDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, FormTeacherAssignments $assignments, StudentDirectory $students, SchoolUserDirectory $users): View
    {
        $arms = $assignments->armsFor($request->user());

        return view('attendance.index', [
            'arms' => $arms,
            'counts' => $students->countsByArmIds($arms->pluck('id')->all()),
            'users' => Gate::allows('manage_attendance') ? $users->activeOptions() : collect(),
            'today' => today('Africa/Lagos')->toDateString(),
        ]);
    }

    public function day(int $arm, Request $request, AttendanceService $attendance): View
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'session' => ['nullable', 'in:Morning,Afternoon'],
        ]);
        $date = $data['date'] ?? today('Africa/Lagos')->toDateString();
        $session = $data['session'] ?? (now('Africa/Lagos')->hour < 12 ? 'Morning' : 'Afternoon');

        return view('attendance.day', [
            ...$attendance->day($arm, $date, $session, $request->user()),
            'editable' => $date === today('Africa/Lagos')->toDateString(),
        ]);
    }

    public function mark(int $arm, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'session' => ['required', 'in:Morning,Afternoon'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:Present,Absent,Late,Excused'],
        ]);
        $attendance->markDay($arm, $data['date'], $data['session'], $data['statuses'], $request->user());

        return back()->with('status', $data['session'].' full-class attendance saved.');
    }

    public function lock(int $arm, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'session' => ['required', 'in:Morning,Afternoon']]);
        $attendance->lockDay($arm, $data['date'], $data['session'], $request->user());

        return back()->with('status', $data['session'].' is locked. Attendance can no longer be changed.');
    }

    public function assign(Request $request, FormTeacherAssignments $assignments): RedirectResponse
    {
        $data = $request->validate(['class_arm_id' => ['required', 'integer'], 'user_id' => ['required', 'integer']]);
        app(SectionAccess::class)->assertClassArm($request->user(), (int) $data['class_arm_id']);
        $assignments->assign((int) $data['class_arm_id'], (int) $data['user_id']);

        return back()->with('status', 'Form teacher assigned.');
    }

    public function alerts(Request $request, AttendanceService $attendance, ClassArmDirectory $arms): View
    {
        $assignedSections = app(SectionAccess::class)->sectionIds($request->user());
        if (! $request->user()->can('view_all_sections') && $assignedSections === []) {
            return view('attendance.alerts', ['locked' => true]);
        }

        $alerts = $attendance->alerts($request->user());
        $labels = $arms->all()->keyBy('id');
        $previewRequests = [];
        if (Gate::forUser($request->user())->allows('manage_attendance')) {
            foreach ($alerts as $alert) {
                $sectionId = $labels[$alert->class_arm_id]?->schoolClass?->level?->section_id;
                if ($sectionId && $alert->session) {
                    $previewRequests[] = [
                        'section_id' => $sectionId,
                        'date' => $alert->date->toDateString(),
                        'session' => $alert->session,
                    ];
                }
            }
        }
        $notHeldPreviews = $attendance->previewNotHeldBatch($previewRequests, $request->user());

        return view('attendance.alerts', ['locked' => false, 'alerts' => $alerts, 'arms' => $labels, 'notHeldPreviews' => $notHeldPreviews]);
    }

    public function settings(Request $request, AttendanceService $attendance, SectionAccess $access): View
    {
        Gate::authorize('manage_attendance');
        $sections = Section::whereIn('id', $access->sectionIds($request->user()))->orderBy('name')->get();
        $sectionId = (int) ($request->integer('section') ?: $sections->first()?->id);
        if (! $sectionId) {
            return view('attendance.settings', ['sections' => $sections, 'section' => null]);
        }

        $previewData = $request->validate([
            'preview_date' => ['nullable', 'required_with:preview_session', 'date_format:Y-m-d'],
            'preview_session' => ['nullable', 'required_with:preview_date', 'in:Morning,Afternoon,Both'],
        ]);
        $notHeldPreview = isset($previewData['preview_date'], $previewData['preview_session'])
            ? $attendance->previewNotHeld($sectionId, $previewData['preview_date'], $previewData['preview_session'], $request->user())
            : null;

        return view('attendance.settings', [
            'sections' => $sections,
            'notHeldPreview' => $notHeldPreview,
            ...$attendance->sectionSettings($sectionId, $request->user()),
        ]);
    }

    public function updateSettings(int $section, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'morning_due_at' => ['required', 'date_format:H:i'],
            'afternoon_due_at' => ['required', 'date_format:H:i'],
        ]);
        $attendance->updateSectionSettings($section, $data['morning_due_at'], $data['afternoon_due_at'], $request->user());

        return back()->with('status', 'Attendance alert times saved. The change was logged.');
    }

    public function markNotHeld(int $section, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'session' => ['required', 'in:Morning,Afternoon,Both']]);
        $report = $attendance->markNotHeld($section, $data['date'], $data['session'], $request->user());
        $alertLabel = $report['open_alerts'] === 1 ? 'open alert was' : 'open alerts were';

        return back()->with([
            'status' => "The selected session was marked not held. {$report['open_alerts']} {$alertLabel} cleared. It is left out of attendance percentages; existing marks stay on record but are not counted. The change was logged.",
            'notHeldReport' => $report,
        ]);
    }

    public function removeNotHeld(int $entry, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $attendance->removeNotHeld($entry, $request->user());

        return back()->with('status', 'The not-held entry was removed and the change was logged.');
    }
}
