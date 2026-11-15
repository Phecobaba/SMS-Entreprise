<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Mail\ApplicationStatusMail;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\RecoveryMailer;
use App\Modules\Core\Services\UsernameRules;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Services\PaystackCredentials;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplicationWorkflow
{
    public const AWAITING = 'Awaiting payment';

    public const REGISTERED = 'Registered';

    public const EXPIRED = 'Expired';

    public const CONFIRMING = 'Still confirming';

    public const FAILED = 'Failed';

    /** @return array{application:Application, token:string,resumed:bool} */
    public function submit(array $data): array
    {
        $settings = SchoolSetting::firstOrCreate([]);
        if (! ($settings->admissions_open ?? true)) {
            throw ValidationException::withMessages(['application' => 'Admissions are closed.']);
        }

        $username = app(UsernameRules::class)->normalize($data['username']);
        if ($error = app(UsernameRules::class)->formatError($username)) {
            throw ValidationException::withMessages(['username' => $error]);
        }
        if ($this->usernameBlocked($username)) {
            throw ValidationException::withMessages(['username' => 'That username is not available. Please choose another.']);
        }

        $token = Str::random(64);
        $fee = ($settings->application_fee_enabled ?? false) ? (int) $settings->application_fee_kobo : 0;
        try {
            [$application, $resumed] = DB::transaction(function () use ($data, $username, $token, $fee): array {
                $existing = Application::where('active_username_key', $username)->lockForUpdate()->first();
                if ($existing) {
                    return [$this->resumeExisting($existing, $data['guardian_email'], $token), true];
                }
                if (app(UsernameRules::class)->isTaken($username)) {
                    throw ValidationException::withMessages(['username' => 'That username is not available. Please choose another.']);
                }

                return [Application::create([
                    ...collect($data)->except(['consent', 'website', 'username'])->all(),
                    'username' => $username,
                    'active_username_key' => $username,
                    'public_reference' => 'APP-'.strtoupper(Str::random(12)),
                    'status' => $fee > 0 ? self::AWAITING : self::REGISTERED,
                    'fee_amount_kobo' => $fee,
                    'currency' => 'NGN',
                    'access_token_hash' => hash('sha256', $token),
                    'consented_at' => now(),
                    'consent_version' => config('admissions.consent_version'),
                    'expires_at' => now()->addDays((int) config('admissions.unpaid_days', 7)),
                    'registered_at' => $fee === 0 ? now() : null,
                ]), false];
            });
        } catch (QueryException $exception) {
            [$application, $resumed] = DB::transaction(function () use ($username, $data, $token): array {
                $existing = Application::where('active_username_key', $username)->lockForUpdate()->first();
                if (! $existing) {
                    throw ValidationException::withMessages(['username' => 'That username is not available. Please choose another.']);
                }

                return [$this->resumeExisting($existing, $data['guardian_email'], $token), true];
            });
        }

        return compact('application', 'token', 'resumed');
    }

    public function byToken(?string $token): ?Application
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return Application::where('access_token_hash', hash('sha256', $token))->first();
    }

    public function status(Application $application): string
    {
        if ($application->status === self::REGISTERED) {
            return self::REGISTERED;
        }
        $attempts = $application->relationLoaded('attempts') ? $application->attempts : null;
        $hasPending = $attempts
            ? $attempts->contains(fn (ApplicationPaymentAttempt $attempt): bool => $attempt->status === 'Pending')
            : $application->attempts()->where('status', 'Pending')->exists();
        if ($application->expires_at->isPast() && ! $hasPending) {
            return self::EXPIRED;
        }
        $attempt = $attempts ? $attempts->sortByDesc('id')->first() : $application->attempts()->latest('id')->first();
        if (! $attempt) {
            return self::AWAITING;
        }
        if ($attempt->status === 'Failed') {
            return self::FAILED;
        }

        return self::CONFIRMING;
    }

    public function continuePayment(Application $application): string
    {
        $pending = $application->attempts()->where('status', 'Pending')
            ->where('active_application_id', $application->id)->latest('id')->first();
        if ($pending) {
            $cutoff = now()->subMinutes((int) config('admissions.payment_attempt_reuse_minutes', 30));
            if ($pending->created_at->gt($cutoff) && $pending->authorization_url) {
                return $pending->authorization_url;
            }
            $result = $this->verifyReference($pending->reference);
            if ($result === self::REGISTERED) {
                return route('applications.status');
            }
            if ($result === self::CONFIRMING) {
                DB::transaction(function () use ($pending): void {
                    $locked = ApplicationPaymentAttempt::whereKey($pending->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status === 'Pending') {
                        $locked->update(['active_application_id' => null, 'last_activity_at' => now()]);
                    }
                });
            }
        }

        return DB::transaction(function () use ($application): string {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === self::REGISTERED) {
                return route('applications.status');
            }
            $active = ApplicationPaymentAttempt::where('application_id', $locked->id)->where('status', 'Pending')
                ->where('active_application_id', $locked->id)->latest('id')->first();
            if ($active?->authorization_url) {
                return $active->authorization_url;
            }

            $reference = 'oak-app-'.app(TenantContext::class)->id().'-'.Str::uuid();
            $attempt = ApplicationPaymentAttempt::create([
                'application_id' => $locked->id, 'active_application_id' => $locked->id,
                'reference' => $reference, 'amount_kobo' => $locked->fee_amount_kobo,
                'currency' => 'NGN', 'status' => 'Pending', 'last_activity_at' => now(),
            ]);
            try {
                $response = Http::withToken(app(PaystackCredentials::class)->secretKeyOrFail())
                    ->acceptJson()->connectTimeout(5)->timeout(15)
                    ->post(rtrim(config('services.paystack.base_url'), '/').'/transaction/initialize', [
                        'email' => $locked->guardian_email, 'amount' => $locked->fee_amount_kobo,
                        'currency' => 'NGN', 'reference' => $reference,
                        'callback_url' => route('applications.return'),
                    ]);
                if (! $response->successful() || $response->json('status') !== true || ! is_string($response->json('data.authorization_url'))) {
                    $attempt->update(['status' => 'Failed', 'active_application_id' => null, 'last_activity_at' => now()]);
                    throw ValidationException::withMessages(['payment' => 'We could not start the payment. Please try again.']);
                }
                $attempt->update(['authorization_url' => $response->json('data.authorization_url')]);

                return $attempt->authorization_url;
            } catch (ConnectionException) {
                $attempt->update(['status' => 'Failed', 'active_application_id' => null, 'last_activity_at' => now()]);
                throw ValidationException::withMessages(['payment' => 'We could not start the payment. Please try again.']);
            }
        });
    }

    public function verifyReference(string $reference): string
    {
        $attempt = ApplicationPaymentAttempt::where('reference', $reference)->firstOrFail();
        if ($attempt->status === 'Verified') {
            return self::REGISTERED;
        }
        if ($attempt->status === 'Failed') {
            return self::FAILED;
        }
        try {
            $response = Http::withToken(app(PaystackCredentials::class)->secretKeyOrFail())
                ->acceptJson()->connectTimeout(5)->timeout(15)
                ->get(rtrim(config('services.paystack.base_url'), '/').'/transaction/verify/'.rawurlencode($reference));
        } catch (ConnectionException) {
            return self::CONFIRMING;
        }
        if (! $response->successful()) {
            return self::CONFIRMING;
        }
        $data = $response->json('data', []);
        if ($response->json('status') !== true || ($data['status'] ?? null) !== 'success') {
            if (($data['status'] ?? null) === 'failed' && ($data['reference'] ?? null) === $reference) {
                $attempt->update(['status' => 'Failed', 'active_application_id' => null, 'last_activity_at' => now()]);

                return self::FAILED;
            }

            return self::CONFIRMING;
        }
        if (($data['reference'] ?? null) !== $reference || ($data['currency'] ?? null) !== 'NGN'
            || ! is_numeric($data['amount'] ?? null) || (int) $data['amount'] !== $attempt->amount_kobo) {
            throw ValidationException::withMessages(['payment' => 'Verified transaction does not match this application.']);
        }

        return $this->finalize($attempt);
    }

    public function finalize(ApplicationPaymentAttempt $attempt): string
    {
        [$application, $send] = DB::transaction(function () use ($attempt): array {
            $attempt = ApplicationPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $application = Application::whereKey($attempt->application_id)->lockForUpdate()->firstOrFail();
            $late = $application->status === self::REGISTERED && $application->confirmed_attempt_id !== $attempt->id;
            $attempt->update(['status' => 'Verified', 'active_application_id' => null, 'last_activity_at' => now(), 'late_success' => $late]);
            if ($late) {
                $application->update(['needs_attention' => true]);

                return [$application, false];
            }
            if ($application->status === self::REGISTERED) {
                return [$application, false];
            }
            $application->update([
                'status' => self::REGISTERED, 'registered_at' => now(),
                'confirmed_attempt_id' => $attempt->id,
            ]);

            return [$application, true];
        });
        if ($send) {
            $this->deferStatusMail($application, 'registered');
        }

        return self::REGISTERED;
    }

    public function checkAgain(Application $application): string
    {
        DB::transaction(function () use ($application): void {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($locked->last_checked_at?->gt(now()->subSeconds(30))) {
                throw ValidationException::withMessages(['payment' => 'Please wait 30 seconds before checking again.']);
            }
            $locked->update(['last_checked_at' => now()]);
        });
        foreach ($application->attempts()->where('status', 'Pending')->latest('id')->get() as $attempt) {
            $status = $this->verifyReference($attempt->reference);
            if ($status === self::REGISTERED) {
                return $status;
            }
        }

        return $this->status($application->fresh());
    }

    public function markPaid(Application $application, User $actor, string $reason): void
    {
        [$record, $send] = DB::transaction(function () use ($application): array {
            $record = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($record->status === self::REGISTERED) {
                return [$record, false];
            }
            $record->update(['status' => self::REGISTERED, 'registered_at' => now()]);

            return [$record, true];
        });
        app(AuditRecorder::class)->record($actor, 'application_marked_paid', $record, ['reason' => $reason], 'Admissions', 'Application marked paid manually.');
        if ($send) {
            $this->deferStatusMail($record, 'registered');
        }
    }

    public function resend(Application $application, User $actor): void
    {
        $this->deferStatusMail($application, $application->status === self::REGISTERED ? 'registered' : 'submitted');
        app(AuditRecorder::class)->record($actor, 'application_confirmation_resent', $application, [], 'Admissions', 'Application status email resent.');
    }

    public function delete(Application $application, User $actor): void
    {
        if ($application->attempts()->where('status', 'Pending')->exists()) {
            throw ValidationException::withMessages(['application' => 'This application has an unresolved payment and cannot be deleted.']);
        }
        app(AuditRecorder::class)->record($actor, 'application_deleted', $application, [], 'Admissions', 'Application deleted.');
        $application->delete();
    }

    public function statusUrl(Application $application): string
    {
        return URL::temporarySignedRoute('applications.status.signed', $application->expires_at, ['application' => $application->public_reference]);
    }

    public function deferStatusMail(Application $application, string $kind): void
    {
        $schoolId = $application->school_id;
        defer(function () use ($application, $kind, $schoolId): void {
            app(TenantContext::class)->set($schoolId);
            $this->sendStatusMail($application->fresh(), $kind);
        }, 'application-status-mail-'.$application->id.'-'.$kind);
    }

    private function sendStatusMail(Application $application, string $kind): void
    {
        try {
            Mail::to($application->guardian_email)->send(new ApplicationStatusMail($kind, $application->public_reference, $this->statusUrl($application)));
            $application->update([$kind === 'registered' ? 'confirmation_email_sent_at' : 'submission_email_sent_at' => now(), 'mail_failed_at' => null]);
        } catch (Throwable $exception) {
            $application->update(['mail_failed_at' => now()]);
            Log::warning('Application status email could not be sent.', [
                'exception_class' => $exception::class,
                'message' => app(RecoveryMailer::class)->scrubFailureMessage(
                    $exception->getMessage(),
                    [$application->guardian_email, $this->statusUrl($application)],
                ),
            ]);
        }
    }

    private function usernameBlocked(string $username): bool
    {
        return in_array($username, ['admin', 'administrator', 'root', 'system', 'support', 'school', 'principal', 'teacher', 'bursar'], true)
            || app(UsernameRules::class)->isTaken($username);
    }

    private function resumeExisting(Application $application, string $guardianEmail, string $token): Application
    {
        $canResume = $application->status !== self::REGISTERED
            && $application->status !== self::EXPIRED
            && $application->expires_at->isFuture()
            && mb_strtolower(trim($application->guardian_email)) === mb_strtolower(trim($guardianEmail));
        if (! $canResume) {
            throw ValidationException::withMessages(['username' => 'That username is not available. Please choose another.']);
        }

        $application->update(['access_token_hash' => hash('sha256', $token)]);

        return $application->fresh();
    }
}
