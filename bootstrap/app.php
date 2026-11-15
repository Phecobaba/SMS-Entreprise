<?php

use App\Modules\Academics\Console\GrantAcademicAccess;
use App\Modules\Academics\Console\MigrateSubjectSections;
use App\Modules\Admissions\Console\AssignDemoRegistrationNumber;
use App\Modules\Admissions\Console\CleanupStudentImports;
use App\Modules\Admissions\Console\GrantAdmissionAccess;
use App\Modules\Admissions\Console\LapseExpiredOffers;
use App\Modules\Admissions\Console\PurgeExpiredApplications;
use App\Modules\Assignments\Console\GrantAssignmentAccess;
use App\Modules\Attendance\Console\GenerateMissingAttendanceAlerts;
use App\Modules\Attendance\Console\GrantAttendanceAccess;
use App\Modules\CBT\Console\CleanupQuestionUploads;
use App\Modules\CBT\Console\GrantCbtAccess;
use App\Modules\CBT\Console\SyncScheduledEntranceOutcomes;
use App\Modules\Core\Console\BootstrapSchoolAdmin;
use App\Modules\Core\Console\GrantSchoolProfileAccess;
use App\Modules\Core\Console\PrepareQaDatabase;
use App\Modules\Core\Console\ResetAdminPassword;
use App\Modules\Core\Console\TestMailConfiguration;
use App\Modules\Core\Http\Middleware\RequirePasswordChange;
use App\Modules\Core\Http\Middleware\ResolveTenant;
use App\Modules\Core\Services\AccountRecovery;
use App\Modules\Core\Services\QaDatabaseProofRoute;
use App\Modules\ENotes\Console\GrantENoteAccess;
use App\Modules\LandingPage\Console\GenerateLandingImages;
use App\Modules\Payments\Console\RecheckPendingPaystackPayments;
use App\Modules\Results\Console\GrantResultAccess;
use App\Modules\Results\Console\MigrateResultSections;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            app(QaDatabaseProofRoute::class)->register(
                Route::getFacadeRoot(),
                app()->environment(),
                env('OAKFIELD_QA_ACTIVE'),
            );
        },
    )
    ->withCommands([GenerateLandingImages::class, BootstrapSchoolAdmin::class, ResetAdminPassword::class, TestMailConfiguration::class, PrepareQaDatabase::class, GrantAcademicAccess::class, MigrateSubjectSections::class, GrantSchoolProfileAccess::class, GrantAdmissionAccess::class, AssignDemoRegistrationNumber::class, CleanupStudentImports::class, PurgeExpiredApplications::class, LapseExpiredOffers::class, GrantResultAccess::class, MigrateResultSections::class, GrantCbtAccess::class, CleanupQuestionUploads::class, SyncScheduledEntranceOutcomes::class, GrantAttendanceAccess::class, GenerateMissingAttendanceAlerts::class, GrantAssignmentAccess::class, GrantENoteAccess::class, RecheckPendingPaystackPayments::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResolveTenant::class);
        $middleware->appendToGroup('web', RequirePasswordChange::class);
        $middleware->validateCsrfTokens(except: ['payments/paystack/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'secret_key', 'temporary_password', 'token']);
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('login') && $request->isMethod('post')) {
                $seconds = max(1, (int) ($exception->getHeaders()['Retry-After'] ?? 60));

                return redirect()->route('login')->with('retry_after', $seconds)
                    ->withInput($request->only('identifier'));
            }
            if ($request->is('forgot-password') && $request->isMethod('post')) {
                $seconds = max(1, (int) ($exception->getHeaders()['Retry-After'] ?? 60));

                return redirect()->route('password.request.confirmation')
                    ->with('recovery_message', AccountRecovery::GENERIC_MESSAGE)
                    ->with('retry_after', $seconds);
            }

            return null;
        });
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if (! $request->user()) {
                return redirect()->route('login')->with('status', 'Your session expired. Please sign in again.');
            }

            return response()->view('errors.419', status: 419);
        });
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }
            if (! $request->user()) {
                return redirect()->route('login')->with('status', 'Your session expired. Please sign in again.');
            }

            return response()->view('errors.419', status: 419);
        });
        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            return response()->view('errors.404', status: 404);
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('results/teaching/*')) {
                return response()->view('errors.403', ['detail' => $exception->getMessage()], 403);
            }

            return null;
        });
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            $reason = $exception->getPrevious();
            if ($request->is('results/teaching/*') && $reason instanceof AuthorizationException) {
                return response()->view('errors.403', ['detail' => $reason->getMessage()], 403);
            }

            return null;
        });
    })->create();
