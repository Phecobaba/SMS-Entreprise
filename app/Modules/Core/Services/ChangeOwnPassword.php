<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangeOwnPassword
{
    public function change(User $user, string $newPassword, ?string $currentPassword, ?string $sessionId, bool $requireCurrent): void
    {
        if ($requireCurrent && (! is_string($currentPassword) || ! Hash::check($currentPassword, $user->password))) {
            throw ValidationException::withMessages(['current_password' => 'The current password is not correct.']);
        }
        $schoolName = (string) School::withoutGlobalScopes()->whereKey($user->school_id)->value('name');
        app(PasswordRules::class)->validate(
            $newPassword,
            $user->username,
            $schoolName,
            $user->password,
            ! $requireCurrent,
        );

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ])->save();
        app(SessionRevoker::class)->others($user, $sessionId);
        app(AuditRecorder::class)->record($user, $requireCurrent ? 'password_changed' : 'password_set', $user);
    }
}
