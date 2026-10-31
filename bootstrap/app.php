<?php

use App\Modules\Academics\Console\GrantAcademicAccess;
use App\Modules\Academics\Console\MigrateSubjectSections;
use App\Modules\Admissions\Console\AssignDemoRegistrationNumber;
use App\Modules\Admissions\Console\GrantAdmissionAccess;
use App\Modules\Admissions\Console\LapseExpiredOffers;
use App\Modules\Assignments\Console\GrantAssignmentAccess;
use App\Modules\Attendance\Console\GenerateMissingAttendanceAlerts;
use App\Modules\Attendance\Console\GrantAttendanceAccess;
use App\Modules\CBT\Console\GrantCbtAccess;
use App\Modules\CBT\Console\SyncScheduledEntranceOutcomes;
use App\Modules\Core\Console\BootstrapSchoolAdmin;
use App\Modules\Core\Console\GrantSchoolProfileAccess;
use App\Modules\Core\Http\Middleware\RequirePasswordChange;
use App\Modules\Core\Http\Middleware\ResolveTenant;
use App\Modules\ENotes\Console\GrantENoteAccess;
use App\Modules\Results\Console\GrantResultAccess;
use App\Modules\Results\Console\MigrateResultSections;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([BootstrapSchoolAdmin::class, GrantAcademicAccess::class, MigrateSubjectSections::class, GrantSchoolProfileAccess::class, GrantAdmissionAccess::class, AssignDemoRegistrationNumber::class, LapseExpiredOffers::class, GrantResultAccess::class, MigrateResultSections::class, GrantCbtAccess::class, SyncScheduledEntranceOutcomes::class, GrantAttendanceAccess::class, GenerateMissingAttendanceAlerts::class, GrantAssignmentAccess::class, GrantENoteAccess::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResolveTenant::class);
        $middleware->appendToGroup('web', RequirePasswordChange::class);
        $middleware->validateCsrfTokens(except: ['payments/paystack/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'secret_key', 'temporary_password']);
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('login') && $request->isMethod('post')) {
                $seconds = max(1, (int) ($exception->getHeaders()['Retry-After'] ?? 60));

                return redirect()->route('login')->with('retry_after', $seconds)
                    ->withInput($request->only('identifier'));
            }

            return null;
        });
    })->create();
