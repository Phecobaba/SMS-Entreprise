<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

class RoleAuthority
{
    private const RANKS = ['Teacher' => 1, 'Bursar' => 2, 'Head' => 3, 'School Admin' => 4];

    public function rank(string $role): ?int
    {
        return self::RANKS[$role] ?? null;
    }

    public function primaryRole(User $user): ?Role
    {
        return $user->roles->sortByDesc(fn (Role $role): int => $this->rank($role->name) ?? 0)->first();
    }

    public function rolesActorMayAssign(User $actor)
    {
        $actorRole = $this->primaryRole($actor);
        $actorRank = $actorRole ? $this->rank($actorRole->name) : null;

        return Role::orderBy('name')->get()->filter(function (Role $role) use ($actorRole, $actorRank): bool {
            $rank = $this->rank($role->name);
            if ($rank === null) {
                return $actorRole?->name === 'School Admin';
            }

            return $actorRank !== null && $rank <= $actorRank;
        })->values();
    }

    public function baseRolesActorMayAssign(User $actor)
    {
        return $this->rolesActorMayAssign($actor)->reject(fn (Role $role): bool => $role->name === 'Head')->values();
    }

    public function primaryBaseRole(User $user): ?Role
    {
        return $user->roles->reject(fn (Role $role): bool => $role->name === 'Head')
            ->sortByDesc(fn (Role $role): int => $this->rank($role->name) ?? 0)->first();
    }

    public function assertMayAssign(User $actor, Role $role, string $message = "You can't create an account with a role above your own."): void
    {
        $actorRole = $this->primaryRole($actor);
        $actorRank = $actorRole ? $this->rank($actorRole->name) : null;
        $targetRank = $this->rank($role->name);
        $allowed = $targetRank === null
            ? $actorRole?->name === 'School Admin'
            : $actorRank !== null && $targetRank <= $actorRank;

        if (! $allowed) {
            throw ValidationException::withMessages(['role_id' => $message]);
        }
    }

    public function assertMayManage(User $actor, User $target): void
    {
        $actorRole = $this->primaryRole($actor);
        $targetRole = $this->primaryRole($target);
        $actorRank = $actorRole ? $this->rank($actorRole->name) : null;
        $targetRank = $targetRole ? $this->rank($targetRole->name) : null;
        if (($targetRank === null && $actorRole?->name !== 'School Admin')
            || $actorRank === null
            || ($targetRank !== null && $targetRank > $actorRank)) {
            throw ValidationException::withMessages(['account' => 'This account belongs to a higher role and is view-only.']);
        }
    }

    public function mayManage(User $actor, User $target): bool
    {
        try {
            $this->assertMayManage($actor, $target);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
