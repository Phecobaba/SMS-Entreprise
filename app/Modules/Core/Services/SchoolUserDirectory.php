<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use App\Modules\StaffHR\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SchoolUserDirectory
{
    public const STAFF_ROLE_NAMES = ['School Admin', 'Head', 'Bursar', 'Teacher'];

    public const PORTAL_ROLE_NAMES = ['Student', 'Parent', 'Candidate'];

    public function find(int $userId): ?User
    {
        return User::find($userId);
    }

    public function createActive(string $name, string $email, string $password): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($email), 'password' => $password, 'status' => 'active']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function activeOptions()
    {
        return User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function activeStaffOptions(): Collection
    {
        return $this->scopeStaffAccounts(User::where('status', User::STATUS_ACTIVE))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function activeStaffAccount(int $userId): User
    {
        return $this->scopeStaffAccounts(User::where('status', User::STATUS_ACTIVE))
            ->findOrFail($userId);
    }

    public function searchStaff(string $term): Collection
    {
        return $this->search($term, fn (Builder $query) => $this->scopeStaffAccounts($query)
            ->whereNotIn('id', Staff::select('user_id')));
    }

    public function searchParents(string $term): Collection
    {
        return $this->search($term, fn ($query) => $query
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'Parent')));
    }

    public function staffAccount(int $userId): User
    {
        return $this->scopeStaffAccounts(User::where('status', 'active'))
            ->whereNotIn('id', Staff::select('user_id'))
            ->findOrFail($userId);
    }

    public function scopeStaffAccounts(Builder $query): Builder
    {
        return $query
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.name', self::STAFF_ROLE_NAMES))
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('roles.name', self::PORTAL_ROLE_NAMES));
    }

    public function parentAccount(int $userId): User
    {
        return User::where('status', 'active')
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'Parent'))
            ->findOrFail($userId);
    }

    public function option(User $user): array
    {
        $user->loadMissing('roles:id,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->roles->pluck('name')->join(', '),
        ];
    }

    private function search(string $term, callable $scope): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $query = User::with('roles:id,name')->where('status', 'active')
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            });
        $scope($query);

        return $query->orderBy('name')->limit(20)->get()->map(fn (User $user): array => $this->option($user));
    }
}
