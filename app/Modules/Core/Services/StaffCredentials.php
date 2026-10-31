<?php

namespace App\Modules\Core\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Notifications\Services\InAppNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffCredentials
{
    private const TEMPORARY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function create(array $data, User $actor): array
    {
        $role = Role::findOrFail((int) $data['role_id']);
        app(RoleAuthority::class)->assertMayAssign($actor, $role);
        $username = app(UsernameRules::class)->validateAvailable((string) $data['username']);
        $sectionIds = $this->validatedSections($role, $data['section_ids'] ?? []);
        $temporaryPassword = $this->temporaryPassword();

        $user = DB::transaction(function () use ($data, $role, $username, $sectionIds, $temporaryPassword, $actor): User {
            $user = new User;
            $user->fill([
                'name' => trim((string) $data['name']),
                'username' => $username,
                'email' => filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null,
                'must_change_password' => true,
                'status' => 'active',
            ]);
            $user->setRawAttributes(array_merge($user->getAttributes(), [
                'password' => Hash::make($temporaryPassword, ['rounds' => config('accounts.temporary_bcrypt_rounds')]),
            ]));
            $user->save();
            $user->roles()->syncWithPivotValues([$role->id], ['school_id' => $user->school_id]);
            $user->sections()->syncWithPivotValues($sectionIds, ['school_id' => $user->school_id]);
            app(AuditRecorder::class)->record($actor, 'staff_account_created', $user, [
                'role' => $role->name,
                'section_ids' => $sectionIds,
            ]);

            return $user;
        });

        if ($role->name === 'School Admin') {
            $this->notifyOtherAdmins($actor, $user, 'Admin account created', $actor->name.' created an Admin account for '.$user->name.'.');
        }

        return ['user' => $user->load(['roles', 'sections']), 'temporary_password' => $temporaryPassword];
    }

    public function reset(User $target, User $actor): array
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages(['account' => 'Use your Account page to change your own password.']);
        }
        app(RoleAuthority::class)->assertMayManage($actor, $target);
        $temporaryPassword = $this->temporaryPassword();

        DB::transaction(function () use ($target, $actor, $temporaryPassword): void {
            $target->setRawAttributes(array_merge($target->getAttributes(), [
                'password' => Hash::make($temporaryPassword, ['rounds' => config('accounts.temporary_bcrypt_rounds')]),
                'must_change_password' => true,
            ]));
            $target->save();
            app(SessionRevoker::class)->all($target);
            app(AuditRecorder::class)->record($actor, 'staff_password_reset', $target);
        });

        if (app(RoleAuthority::class)->primaryRole($target)?->name === 'School Admin') {
            $this->notifyOtherAdmins($actor, $target, 'Admin password reset', $actor->name.' reset the password for '.$target->name.'.');
        }

        return ['user' => $target->load(['roles', 'sections']), 'temporary_password' => $temporaryPassword];
    }

    public function changeAccess(User $target, Role $role, array $sectionIds, User $actor): User
    {
        if ($target->is($actor)) {
            throw ValidationException::withMessages(['account' => 'Use your Account page for your own account.']);
        }
        app(RoleAuthority::class)->assertMayManage($actor, $target);
        app(RoleAuthority::class)->assertMayAssign($actor, $role, "You can't give a role above your own.");
        app(LastActiveAdminGuard::class)->assertCanLoseAdminRole($target, $role->name);
        $sectionIds = $this->validatedSections($role, $sectionIds);

        return DB::transaction(function () use ($target, $role, $sectionIds, $actor): User {
            $beforeRole = app(RoleAuthority::class)->primaryRole($target)?->name;
            $rankedRoleIds = Role::whereIn('name', ['School Admin', 'Principal', 'Bursar', 'Teacher'])->pluck('id');
            $target->roles()->detach($rankedRoleIds);
            $target->roles()->syncWithoutDetaching([$role->id => ['school_id' => $target->school_id]]);
            $target->sections()->syncWithPivotValues($sectionIds, ['school_id' => $target->school_id]);
            app(AuditRecorder::class)->record($actor, 'staff_access_changed', $target, [
                'before_role' => $beforeRole,
                'after_role' => $role->name,
                'section_ids' => $sectionIds,
            ]);
            app(SessionRevoker::class)->all($target);

            return $target->load(['roles.permissions', 'sections']);
        });
    }

    private function validatedSections(Role $role, array $sectionIds): array
    {
        $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));
        $count = Section::whereIn('id', $sectionIds)->count();
        if ($count !== count($sectionIds)) {
            throw ValidationException::withMessages(['section_ids' => 'Choose Sections from this school only.']);
        }
        if ($role->name === 'Principal' && count($sectionIds) !== 1) {
            throw ValidationException::withMessages(['section_ids' => 'A Head must have exactly one Section.']);
        }
        if ($role->name === 'Teacher' && count($sectionIds) < 1) {
            throw ValidationException::withMessages(['section_ids' => 'A Teacher must have at least one Section.']);
        }
        if (in_array($role->name, ['School Admin', 'Bursar'], true) && $sectionIds !== []) {
            throw ValidationException::withMessages(['section_ids' => 'Admin and Bursar accounts do not have a Section assignment.']);
        }

        return $sectionIds;
    }

    private function temporaryPassword(): string
    {
        $value = '';
        for ($index = 0; $index < 12; $index++) {
            $value .= self::TEMPORARY_ALPHABET[random_int(0, strlen(self::TEMPORARY_ALPHABET) - 1)];
        }

        return $value;
    }

    private function notifyOtherAdmins(User $actor, User $subject, string $title, string $body): void
    {
        $adminIds = User::where('id', '!=', $actor->id)
            ->whereHas('roles', fn ($query) => $query->where('roles.name', 'School Admin'))
            ->pluck('id')->all();
        app(InAppNotifications::class)->send($adminIds, $title, $body, '/staff/access/'.$subject->id);
    }
}
