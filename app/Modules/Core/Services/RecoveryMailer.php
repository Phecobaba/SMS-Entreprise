<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Mail\AdminPasswordChangedMail;
use App\Modules\Core\Mail\PasswordChangedMail;
use App\Modules\Core\Mail\PasswordResetLinkMail;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class RecoveryMailer
{
    /** @return array{outcome:string,exception_class?:string,message?:string} */
    public function resetLink(User $user, string $resetUrl): array
    {
        if (! $this->available()) {
            return [
                'outcome' => 'send_failed',
                'exception_class' => RuntimeException::class,
                'message' => 'The configured mailer cannot deliver password reset email.',
            ];
        }

        return $this->attempt($user->email, new PasswordResetLinkMail(
            $user->name,
            $resetUrl,
            (int) config('account_recovery.expire_minutes'),
        ));
    }

    public function passwordChanged(User $user): void
    {
        if (! $user->email || ! $this->available()) {
            return;
        }

        $this->send($user->email, new PasswordChangedMail($user->name));
    }

    /** @param iterable<User> $admins */
    public function adminNotice(iterable $admins, User $account): void
    {
        if (! $this->available()) {
            return;
        }

        foreach ($admins as $admin) {
            if ($admin->email) {
                $this->send($admin->email, new AdminPasswordChangedMail($admin->name, $account->name));
            }
        }
    }

    public function available(): bool
    {
        return ! $this->includesLogTransport((string) config('mail.default'));
    }

    private function includesLogTransport(string $mailer, array $seen = []): bool
    {
        if ($mailer === '' || in_array($mailer, $seen, true)) {
            return true;
        }
        $seen[] = $mailer;
        $settings = config("mail.mailers.{$mailer}");
        if (! is_array($settings)) {
            return true;
        }
        if (($settings['transport'] ?? null) === 'log') {
            return true;
        }
        foreach ($settings['mailers'] ?? [] as $nested) {
            if ($this->includesLogTransport((string) $nested, $seen)) {
                return true;
            }
        }

        return false;
    }

    private function send(string $address, object $mail): void
    {
        $result = $this->attempt($address, $mail);
        if ($result['outcome'] === 'send_failed') {
            Log::warning(sprintf(
                'Account recovery email failed [%s]: %s',
                $result['exception_class'],
                $result['message'],
            ));
        }
    }

    /** @return array{outcome:string,exception_class?:string,message?:string} */
    private function attempt(string $address, object $mail): array
    {
        try {
            $sentMessage = Mail::to($address)->send($mail);

            return ['outcome' => $sentMessage === null ? 'queued_for_send' : 'sent'];
        } catch (Throwable $exception) {
            return [
                'outcome' => 'send_failed',
                'exception_class' => $exception::class,
                'message' => $this->scrubFailureMessage($exception->getMessage(), [$address, $mail->resetUrl ?? null]),
            ];
        }
    }

    /** @param array<int, string|null> $secrets */
    public function scrubFailureMessage(string $message, array $secrets = []): string
    {
        foreach (array_filter($secrets, fn (?string $value): bool => filled($value)) as $secret) {
            $message = str_replace($secret, '[redacted]', $message);
        }

        $message = preg_replace('~https?://\S+~i', '[redacted-url]', $message) ?? '[redacted]';
        $message = preg_replace('~\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b~i', '[redacted-address]', $message) ?? '[redacted]';
        $message = preg_replace('~(?i)\b(token|username|user|password)\s*[:=]\s*\S+~', '$1=[redacted]', $message) ?? '[redacted]';

        return trim($message) === '' ? '[no transport message]' : $message;
    }
}
