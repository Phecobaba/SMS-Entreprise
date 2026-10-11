<?php

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
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
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today('Africa/Lagos')->toDateString();

        return view('attendance.day', [
            ...$attendance->day($arm, $date, $request->user()),
            'editable' => $date === today('Africa/Lagos')->toDateString(),
        ]);
    }

    public function mark(int $arm, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:Present,Absent,Late,Excused'],
        ]);
        $attendance->markDay($arm, $data['date'], $data['statuses'], $request->user());

        return back()->with('status', 'Full-class attendance saved.');
    }

    public function lock(int $arm, Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $attendance->lockDay($arm, $data['date'], $request->user());

        return back()->with('status', 'This day is locked. Attendance can no longer be changed.');
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
        $alerts = $attendance->alerts($request->user());
        $labels = $arms->all()->keyBy('id');

        return view('attendance.alerts', ['alerts' => $alerts, 'arms' => $labels]);
    }
}
