<?php

namespace App\Modules\StaffHR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Core\Services\StaffSignIn;
use App\Modules\StaffHR\Models\Staff;
use App\Modules\StaffHR\Models\StaffDisciplinaryLog;
use App\Modules\StaffHR\Models\StaffDocument;
use App\Modules\StaffHR\Models\StaffExitRecord;
use App\Modules\StaffHR\Services\StaffRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request, SchoolUserDirectory $users): View
    {
        $selected = null;
        if ($request->integer('user')) {
            $account = $users->staffAccount($request->integer('user'));
            $selected = $users->option($account);
        }

        return view('staff.index', [
            'staff' => Staff::with('user')->latest()->paginate(20),
            'selectedUser' => $selected,
        ]);
    }

    public function searchAccounts(Request $request, SchoolUserDirectory $users): JsonResponse
    {
        $term = (string) $request->query('q', '');
        if (mb_strlen(trim($term)) < 2) {
            return response()->json(['message' => 'Enter at least 2 characters.', 'results' => []]);
        }

        return response()->json(['results' => $users->searchStaff($term)]);
    }

    public function store(Request $request, SchoolUserDirectory $users): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'], 'role_title' => ['required', 'string', 'max:255'],
            'employment_date' => ['required', 'date'], 'contract_type' => ['required', 'string', 'max:50'],
        ]);
        $users->staffAccount((int) $data['user_id']);
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

    public function edit(int $staff): View
    {
        return view('staff.edit', ['staff' => Staff::with('user')->findOrFail($staff)]);
    }

    public function update(int $staff, Request $request, AuditRecorder $audit): RedirectResponse
    {
        $record = Staff::findOrFail($staff);
        $data = $request->validate([
            'role_title' => ['required', 'string', 'max:255'],
            'employment_date' => ['required', 'date'],
            'contract_type' => ['required', 'string', 'max:50'],
        ]);
        $before = $record->only(['role_title', 'employment_date', 'contract_type', 'status']);
        $record->update($data);
        $audit->record($request->user(), 'staff_employment_record_updated', $record, [
            'before' => $before,
            'after' => $record->only(['role_title', 'employment_date', 'contract_type', 'status']),
        ]);

        return redirect()->route('staff.show', $record)->with('status', 'Employment record saved.');
    }

    public function salary(int $staff, Request $request, StaffRecords $records): View
    {
        $record = Staff::with('user')->findOrFail($staff);

        return view('staff.salary', ['staff' => $record, 'history' => $records->salaries($record, $request->user())]);
    }

    public function addSalary(int $staff, Request $request, StaffRecords $records): RedirectResponse
    {
        $data = $request->validate([
            'amount_naira' => ['required', 'regex:/^(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]{1,2})?$/'],
            'effective_from' => ['required', 'date'],
        ]);
        $amountKobo = $this->nairaToKobo($data['amount_naira']);
        if ($amountKobo < 1) {
            return back()->withErrors(['amount_naira' => 'Enter a positive salary amount in naira.'])->withInput();
        }
        $records->addSalary(Staff::findOrFail($staff), $amountKobo, $data['effective_from'], $request->user());

        return redirect()->route('staff.salary', $staff)->with('status', 'Salary history updated.');
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

        return redirect()->route('staff.discipline', $staff)->with('status', 'Disciplinary entry saved.');
    }

    public function document(int $staff, Request $request): RedirectResponse
    {
        Staff::findOrFail($staff);
        $data = $request->validate(['document_type' => ['required', 'string', 'max:100'], 'file' => ['required', 'file', 'max:10240']]);
        $path = $data['file']->store('staff-documents/'.$staff, 'local');
        StaffDocument::create(['staff_id' => $staff, 'document_type' => $data['document_type'], 'file_path' => $path, 'uploaded_at' => now()]);

        return redirect()->route('staff.show', $staff)->with('status', 'Private staff document uploaded.');
    }

    public function download(int $staff, int $document)
    {
        $record = StaffDocument::where('staff_id', $staff)->findOrFail($document);

        return Storage::disk('local')->download($record->file_path);
    }

    public function exit(int $staff, Request $request, StaffSignIn $signIn, AuditRecorder $audit): RedirectResponse
    {
        $today = CarbonImmutable::now('Africa/Lagos')->toDateString();
        $data = $request->validate([
            'exit_type' => ['required', Rule::in(['Resigned', 'Terminated', 'Sacked'])],
            'exit_date' => ['required', 'date', 'before_or_equal:'.$today], 'notice_period' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'exit_date.before_or_equal' => 'The exit date cannot be later than today.',
        ]);
        $actor = $request->user()->loadMissing('roles');
        DB::transaction(function () use ($staff, $data, $actor, $signIn, $audit): void {
            $record = Staff::whereKey($staff)->lockForUpdate()->firstOrFail();
            $account = User::whereKey($record->user_id)->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['account' => 'This staff member has no user account.']);
            }
            $account->loadMissing('roles');
            $signIn->disableForRelatedAction($account, $actor);
            StaffExitRecord::updateOrCreate(['staff_id' => $staff], $data);
            $record->update(['status' => $data['exit_type'] === 'Sacked' ? 'Terminated' : $data['exit_type']]);
            $audit->record(
                $actor,
                'staff_exited',
                $record,
                ['exit_type' => $data['exit_type'], 'exit_date' => $data['exit_date']],
                'Staff HR',
                'Recorded '.$data['exit_type'].' exit for '.$account->name.' and disabled sign-in.',
            );
        });

        return redirect()->route('staff.show', $staff)->with('status', 'Staff exit recorded.');
    }

    private function nairaToKobo(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '', trim($amount)), 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
