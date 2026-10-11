<?php

namespace App\Modules\StaffHR\Services;

use App\Modules\Core\Models\User;
use App\Modules\StaffHR\Models\Staff;
use App\Modules\StaffHR\Models\StaffDisciplinaryLog;
use App\Modules\StaffHR\Models\StaffSalaryHistory;
use App\Modules\StaffHR\Models\StaffSensitiveViewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffRecords
{
    public function addSalary(Staff $staff, int $amountKobo, string $effectiveFrom, User $actor): StaffSalaryHistory
    {
        Gate::forUser($actor)->authorize('manage_staff_records');
        if ($amountKobo < 1) {
            throw ValidationException::withMessages(['amount_kobo' => 'Enter a positive salary amount.']);
        }

        return DB::transaction(function () use ($staff, $amountKobo, $effectiveFrom): StaffSalaryHistory {
            Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $latest = StaffSalaryHistory::where('staff_id', $staff->id)->orderByDesc('effective_from')->lockForUpdate()->first();
            if ($latest && $effectiveFrom <= $latest->effective_from->toDateString()) {
                throw ValidationException::withMessages(['effective_from' => 'New salary must start after the previous salary began.']);
            }
            if ($latest) {
                $latest->update(['effective_to' => date('Y-m-d', strtotime($effectiveFrom.' -1 day'))]);
            }

            return StaffSalaryHistory::create([
                'staff_id' => $staff->id, 'amount_kobo' => $amountKobo,
                'effective_from' => $effectiveFrom,
            ]);
        });
    }

    public function salaries(Staff $staff, User $viewer)
    {
        Gate::forUser($viewer)->authorize('view_staff_salary');
        StaffSensitiveViewAudit::create(['staff_id' => $staff->id, 'viewed_by' => $viewer->id, 'data_type' => 'Salary', 'viewed_at' => now()]);

        return StaffSalaryHistory::where('staff_id', $staff->id)->orderByDesc('effective_from')->paginate(20);
    }

    public function discipline(Staff $staff, User $viewer)
    {
        Gate::forUser($viewer)->authorize('view_staff_disciplinary');
        StaffSensitiveViewAudit::create(['staff_id' => $staff->id, 'viewed_by' => $viewer->id, 'data_type' => 'Disciplinary', 'viewed_at' => now()]);

        return StaffDisciplinaryLog::where('staff_id', $staff->id)->orderByDesc('date')->paginate(20);
    }
}
