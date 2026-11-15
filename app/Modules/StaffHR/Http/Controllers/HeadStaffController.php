<?php

namespace App\Modules\StaffHR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\StaffHR\Models\StaffDisciplinaryLog;
use App\Modules\StaffHR\Models\StaffSensitiveViewAudit;
use App\Modules\StaffHR\Services\HeadStaffScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HeadStaffController extends Controller
{
    public function index(Request $request, HeadStaffScope $scope): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $sections = $scope->sectionIds($request->user());

        return view('staff.head-index', [
            'locked' => $sections->isEmpty(),
            'sectionNames' => $request->user()->sections()->whereIn('sections.id', $sections)->orderBy('sections.name')->pluck('sections.name'),
            'staff' => $sections->isEmpty() ? null : $scope->list($sections, $filters['q'] ?? null),
            'search' => $filters['q'] ?? '',
        ]);
    }

    public function show(int $staff, Request $request, HeadStaffScope $scope): View
    {
        $sections = $scope->sectionIds($request->user());
        abort_if($sections->isEmpty(), 404);
        $record = $scope->find($sections, $staff);
        StaffSensitiveViewAudit::create([
            'staff_id' => $record->id,
            'viewed_by' => $request->user()->id,
            'data_type' => 'Disciplinary',
            'viewed_at' => now(),
        ]);
        $entries = StaffDisciplinaryLog::query()
            ->select(['id', 'staff_id', 'type', 'date', 'description', 'issued_by', 'resolution'])
            ->with('issuer:id,name')
            ->where('staff_id', $record->id)
            ->latest('date')->latest('id')->paginate(20);

        return view('staff.head-show', [
            'profile' => $scope->profile($record, $sections),
            'entries' => $entries,
        ]);
    }

    public function storeDiscipline(int $staff, Request $request, HeadStaffScope $scope, AuditRecorder $audit): RedirectResponse
    {
        Gate::authorize('record_section_discipline');
        $sections = $scope->sectionIds($request->user());
        abort_if($sections->isEmpty(), 404);
        $record = $scope->find($sections, $staff);
        $today = CarbonImmutable::now('Africa/Lagos')->toDateString();
        $data = $request->validate([
            'type' => ['required', Rule::in(['Query', 'Warning', 'Commendation'])],
            'date' => ['required', 'date', 'before_or_equal:'.$today],
            'description' => ['required', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ], ['date.before_or_equal' => 'The date cannot be later than today.']);
        DB::transaction(function () use ($data, $record, $request, $audit): void {
            $entry = StaffDisciplinaryLog::create($data + [
                'staff_id' => $record->id,
                'issued_by' => $request->user()->id,
            ]);
            $audit->record(
                $request->user(),
                'section_staff_discipline_recorded',
                $entry,
                ['staff_id' => $record->id, 'type' => $entry->type, 'date' => $entry->date->toDateString()],
                'Staff HR',
                'Recorded a '.$entry->type.' discipline entry for '.$record->user->name.'.',
            );
        });

        return redirect()->route('head.staff.show', $record->id)->with('status', 'Discipline entry recorded.');
    }
}
