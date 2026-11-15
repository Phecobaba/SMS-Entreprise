<?php

namespace App\Modules\Core\Services;

use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Mail\ConfirmAccountEmailMail;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class ParentEmail
{
    public function change(User $parent, string $email, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $parent->password)) {
            throw ValidationException::withMessages(['current_password' => 'The password is incorrect.']);
        }

        $this->saveUnconfirmed($parent, $email);
    }

    public function saveFromPayment(User $parent, string $email): void
    {
        if (filled($parent->email)) {
            return;
        }

        $this->saveUnconfirmed($parent, $email);
    }

    public function confirm(User $parent, string $emailHash): void
    {
        abort_unless(app(ParentAccountDirectory::class)->existsForUser($parent->id), 403);
        abort_unless(filled($parent->email) && hash_equals(hash('sha256', $parent->email), $emailHash), 403);
        abort_if($parent->email_verified_at !== null, 410, 'This confirmation link has already been used.');

        $parent->forceFill(['email_verified_at' => now()])->save();
    }

    private function saveUnconfirmed(User $parent, string $email): void
    {
        abort_unless(app(ParentAccountDirectory::class)->existsForUser($parent->id), 403);
        $email = mb_strtolower(trim($email));
        if (User::whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($parent->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
        }

        $parent->forceFill(['email' => $email, 'email_verified_at' => null])->save();
        $url = URL::temporarySignedRoute('account.email.confirm', now()->addMinutes(60), [
            'user' => $parent->id,
            'email' => hash('sha256', $email),
        ]);

        defer(function () use ($parent, $url, $email): void {
            try {
                Mail::to($email)->send(new ConfirmAccountEmailMail($parent->name, $url));
            } catch (Throwable $exception) {
                Log::warning('Parent email confirmation could not be sent.', [
                    'exception_class' => $exception::class,
                    'message' => app(RecoveryMailer::class)->scrubFailureMessage($exception->getMessage(), [$email, $url]),
                ]);
            }
        }, 'parent-email-confirmation-'.$parent->id);
    }
}
