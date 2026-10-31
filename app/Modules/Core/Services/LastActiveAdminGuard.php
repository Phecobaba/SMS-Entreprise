<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

class LastActiveAdminGuard
{
    public function assertCanLoseAdminRole(User $user, string $newRole): void
    {
        if ($newRole === 'School Admin'
            || ! $user->roles()->where('roles.name', 'School Admin')->exists()) {
            return;
        }

        $otherActiveAdmins = User::whereKeyNot($user->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('roles.name', 'School Admin'))
            ->exists();

        if (! $otherActiveAdmins) {
            throw ValidationException::withMessages([
                'role_id' => 'The last active Admin cannot be demoted. Add or re-enable another Admin first.',
            ]);
        }
    }
}
