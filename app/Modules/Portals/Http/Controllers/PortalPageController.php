<?php

namespace App\Modules\Portals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\ENotes\Services\ENoteService;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Portals\Services\PortalContext;
use App\Modules\Results\Services\ResultCards;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalPageController extends Controller
{
    public function fees(Request $request, PortalContext $portal, PaymentLedger $ledger): View
    {
        $context = $this->context($request, $portal);
        if (! $context['student_id']) {
            return view('portals.empty', compact('context'));
        }

        return view('portals.fees', ['context' => $context,
            'fees' => $ledger->portalInvoices($context['student_id'], $request->user())]);
    }

    public function results(Request $request, PortalContext $portal, ResultCards $cards): View
    {
        $context = $this->context($request, $portal);
        if (! $context['student_id']) {
            return view('portals.empty', compact('context'));
        }

        return view('portals.results', ['context' => $context,
            'cards' => $cards->portalList($context['student_id'], $request->user())]);
    }

    public function attendance(Request $request, PortalContext $portal, AttendanceService $attendance): View
    {
        $context = $this->context($request, $portal);
        if (! $context['student_id']) {
            return view('portals.empty', compact('context'));
        }
        $data = null;
        $locked = false;
        if ($context['student_id']) {
            try {
                $data = $attendance->portalRecords($context['student_id'], $request->user());
            } catch (AuthorizationException) {
                $locked = true;
            }
        }

        return view('portals.attendance', compact('context', 'data', 'locked'));
    }

    public function enotes(Request $request, PortalContext $portal, ENoteService $notes): View
    {
        $context = $this->context($request, $portal);
        abort_unless($context['kind'] === 'student', 403);
        $data = $request->validate(['subject' => ['nullable', 'integer']]);

        return view('portals.enotes', ['context' => $context,
            ...$notes->studentListing($request->user(), $data['subject'] ?? null)]);
    }

    public function downloadEnote(int $note, Request $request, ENoteService $notes): StreamedResponse
    {
        $item = $notes->studentAccessible($note, $request->user());
        abort_unless($item->file_path && Storage::disk('local')->exists($item->file_path), 404);

        return Storage::disk('local')->download($item->file_path);
    }

    public function profile(Request $request, PortalContext $portal): View
    {
        $context = $this->context($request, $portal);
        if (! $context['student_id']) {
            return view('portals.empty', compact('context'));
        }
        $student = $context['kind'] === 'student' ? app(StudentDirectory::class)->forUser($request->user()) : null;

        return view('portals.profile', ['context' => $context, 'student' => $student,
            'class' => $student ? app(ClassArmDirectory::class)->label($student->class_arm_id) : null,
            'section' => $student ? app(SectionAccess::class)->sectionForArm($student->class_arm_id)?->name : null]);
    }

    private function context(Request $request, PortalContext $portal): array
    {
        $context = $portal->for($request);
        abort_unless($context, 403);

        return $context;
    }
}
