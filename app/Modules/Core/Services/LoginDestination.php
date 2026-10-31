<?php

namespace App\Modules\Core\Services;

use App\Modules\Admissions\Services\CandidateAccount;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Gate;

class LoginDestination
{
    public function for(User $user): string
    {
        if ($user->must_change_password) {
            return route('password.set.show');
        }
        if (Gate::forUser($user)->allows('view_dashboard')
            || app(StudentDirectory::class)->forUser($user)
            || app(ParentAccountDirectory::class)->existsForUser($user->id)) {
            return route('dashboard');
        }
        if (app(CandidateAccount::class)->existsForUser($user->id)) {
            return route('admissions.mine');
        }
        foreach ([
            'mark_attendance' => 'attendance.index',
            'mark_assignments' => 'assignments.index',
            'upload_enotes' => 'enotes.index',
            'view_attendance_alerts' => 'attendance.alerts',
            'enter_results' => 'results.index',
        ] as $permission => $route) {
            if (Gate::forUser($user)->allows($permission)) {
                return route($route);
            }
        }

        return route('dashboard');
    }
}
