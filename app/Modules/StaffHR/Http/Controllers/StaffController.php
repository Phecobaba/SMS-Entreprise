<?php

namespace App\Modules\StaffHR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\StaffHR\Models\Staff;
use App\Modules\StaffHR\Models\StaffDisciplinaryLog;
use App\Modules\StaffHR\Models\StaffDocument;
use App\Modules\StaffHR\Models\StaffExitRecord;
use App\Modules\StaffHR\Services\StaffRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('staff.index', [
            'staff' => Staff::with('user')->latest()->paginate(20),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'role_title' => ['required', 'string', 'max:255'],
            'employment_date' => ['required', 'date'], 'contract_type' => ['required', 'string', 'max:50'],
        ]);
        User::findOrFail($data['user_id']);
        $staff = Staff::create($data + ['status' => 'Active']);

        return redirect()->route('staff.show', $staff)->with('status', 'Staff record created.');
    }

    public function show(int $staff): View
    {
        return view('staff.show', ['staff' => Staff::with('user')->findOrFail($staff),
            'documents' => StaffDocument::where('staff_id', $staff)->latest()->paginate(20),
            'exit' => StaffExitRecord::where('staff_id', $staff)->first(),
        ]);
    }

    public function salary(int $staff, Request $request, StaffRecords $records): View
    {
        $record = Staff::with('user')->findOrFail($staff);

        return view('staff.salary', ['staff' => $record, 'history' => $records->salaries($record, $request->user())]);
    }

    public function addSalary(int $staff, Request $request, StaffRecords $records): RedirectResponse
    {
        $data = $request->validate(['amount_kobo' => ['required', 'integer', 'min:1'], 'effective_from' => ['required', 'date']]);
        $records->addSalary(Staff::findOrFail($staff), (int) $data['amount_kobo'], $data['effective_from'], $request->user());

        return back()->with('status', 'Salary history updated.');
    }

    public function discipline(int $staff, Request $request, StaffRecords $records): View
    {
        $record = Staff::with('user')->findOrFail($staff);

        return view('staff.discipline', ['staff' => $record, 'entries' => $records->discipline($record, $request->user())]);
    }

    public function addDiscipline(int $staff, Request $request): RedirectResponse
    {
        Staff::findOrFail($staff);
        $data = $request->validate([
            'type' => ['required', Rule::in(['Query', 'Warning', 'Commendation'])],
            'date' => ['required', 'date'], 'description' => ['required', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);
        StaffDisciplinaryLog::create($data + ['staff_id' => $staff, 'issued_by' => $request->user()->id]);

        return back()->with('status', 'Disciplinary entry saved.');
    }

    public function document(int $staff, Request $request): RedirectResponse
    {
        Staff::findOrFail($staff);
        $data = $request->validate(['document_type' => ['required', 'string', 'max:100'], 'file' => ['required', 'file', 'max:10240']]);
        $path = $data['file']->store('staff-documents/'.$staff, 'local');
        StaffDocument::create(['staff_id' => $staff, 'document_type' => $data['document_type'], 'file_path' => $path, 'uploaded_at' => now()]);

        return back()->with('status', 'Private staff document uploaded.');
    }

    public function download(int $staff, int $document)
    {
        $record = StaffDocument::where('staff_id', $staff)->findOrFail($document);

        return Storage::disk('local')->download($record->file_path);
    }

    public function exit(int $staff, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'exit_type' => ['required', Rule::in(['Resigned', 'Terminated', 'Sacked'])],
            'exit_date' => ['required', 'date'], 'notice_period' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($staff, $data): void {
            $record = Staff::whereKey($staff)->lockForUpdate()->firstOrFail();
            StaffExitRecord::updateOrCreate(['staff_id' => $staff], $data);
            $record->update(['status' => $data['exit_type'] === 'Sacked' ? 'Terminated' : $data['exit_type']]);
        });

        return back()->with('status', 'Staff exit recorded.');
    }
}
