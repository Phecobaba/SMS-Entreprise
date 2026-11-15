<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\AccountPasswordResetToken;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use App\Modules\Notifications\Services\InAppNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountRecovery
{
    public const GENERIC_MESSAGE = 'If an eligible account matches those details, we have sent a password-reset link. Check your inbox or ask the school office.';

    /** @return array{message:string,retry_after:?int} */
    public function request(string $identifier): array
    {
        $identifier = mb_strtolower(trim($identifier));
        $key = $this->identifierThrottleKey($identifier);
        $ipKey = $this->ipThrottleKey(request()->ip());
        $attempts = max(1, (int) config('account_recovery.identifier_attempts'));
        $ipAttempts = max(1, (int) config('account_recovery.ip_attempts'));

        if (RateLimiter::tooManyAttempts($key, $attempts) || RateLimiter::tooManyAttempts($ipKey, $ipAttempts)) {
            $this->logOutcome('throttled');

            return ['message' => self::GENERIC_MESSAGE, 'retry_after' => null];
        }
        $decay = max(1, (int) config('account_recovery.identifier_decay_seconds'));
        RateLimiter::hit($key, $decay);
        RateLimiter::hit($ipKey, max(1, (int) config('account_recovery.ip_decay_seconds', $decay)));

        $column = str_contains($identifier, '@') ? 'email' : 'username';
        $user = User::query()
            ->whereRaw('LOWER('.$column.') = ?', [$identifier])
            ->first();

        if (! $user) {
            $this->logOutcome('no_matching_account');

            return ['message' => self::GENERIC_MESSAGE, 'retry_after' => null];
        }
        if (! filled($user->email)) {
            $this->logOutcome('account_has_no_email');

            return ['message' => self::GENERIC_MESSAGE, 'retry_after' => null];
        }
        if (! $this->enabledFor($user)) {
            $this->logOutcome('not_eligible');

            return ['message' => self::GENERIC_MESSAGE, 'retry_after' => null];
        }

        $plain = Str::random(64);
        DB::transaction(function () use ($user, $plain): void {
            AccountPasswordResetToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);
            AccountPasswordResetToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addMinutes((int) config('account_recovery.expire_minutes')),
            ]);
        });

        $resetUrl = route('password.reset', ['token' => $plain]);
        defer(
            function () use ($user, $resetUrl): void {
                $mailer = app(RecoveryMailer::class);
                try {
                    $result = $mailer->resetLink($user, $resetUrl);
                } catch (Throwable $exception) {
                    $result = [
                        'outcome' => 'send_failed',
                        'exception_class' => $exception::class,
                        'message' => $mailer->scrubFailureMessage($exception->getMessage(), [$user->email, $resetUrl]),
                    ];
                }
                $this->logOutcome(
                    $result['outcome'],
                    $result['exception_class'] ?? null,
                    $result['message'] ?? null,
                );
            },
            'account-recovery-mail-'.$user->id,
        );

        return ['message' => self::GENERIC_MESSAGE, 'retry_after' => null];
    }

    public function token(string $plain): ?AccountPasswordResetToken
    {
        $token = AccountPasswordResetToken::with('user')
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        return $token?->usable() ? $token : null;
    }

    public function reset(string $plain, string $password): User
    {
        $hash = hash('sha256', $plain);
        $user = DB::transaction(function () use ($hash, $password): User {
            $token = AccountPasswordResetToken::where('token_hash', $hash)->lockForUpdate()->first();
            if (! $token || ! $token->usable()) {
                throw ValidationException::withMessages(['token' => 'This reset link has expired or has already been used.']);
            }
            $user = User::findOrFail($token->user_id);
            $schoolName = (string) DB::table('schools')->where('id', $user->school_id)->value('name');
            app(PasswordRules::class)->validate($password, $user->username, $schoolName);

            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => false,
                'remember_token' => null,
            ])->save();
            $token->update(['used_at' => now()]);
            app(SessionRevoker::class)->all($user);
            app(AuditRecorder::class)->record($user, 'password_self_service_reset', $user);

            return $user;
        });

        $admins = User::whereKeyNot($user->id)
            ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'view_all_sections'))
            ->get();
        app(InAppNotifications::class)->send(
            $admins->pluck('id')->all(),
            'Admin password changed',
            $user->name.' changed their password through account recovery.',
            '/staff/access/'.$user->id,
        );
        app(RecoveryMailer::class)->passwordChanged($user);
        app(RecoveryMailer::class)->adminNotice($admins, $user);

        return $user;
    }

    public function enabledFor(User $user): bool
    {
        if ($user->status !== 'active' || ! filled($user->email) || $user->email_verified_at === null) {
            return false;
        }
        $permission = trim((string) config('account_recovery.enabled_permission'));

        return $permission === '' || $user->can($permission);
    }

    private function identifierThrottleKey(string $identifier): string
    {
        return 'account-recovery:'.app(TenantContext::class)->id().':'.hash('sha256', $identifier);
    }

    private function ipThrottleKey(?string $ip): string
    {
        return 'account-recovery-ip:'.app(TenantContext::class)->id().':'.hash('sha256', $ip ?? 'unknown');
    }

    private function logOutcome(string $outcome, ?string $exceptionClass = null, ?string $message = null): void
    {
        $line = 'Password reset request outcome='.$outcome.'.';
        if ($outcome === 'send_failed') {
            $line .= sprintf(' [%s]: %s', $exceptionClass ?? 'unknown', $message ?? '[no transport message]');
            Log::warning($line);

            return;
        }

        Log::info($line);
    }
}
