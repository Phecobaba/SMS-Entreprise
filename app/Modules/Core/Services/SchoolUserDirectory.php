<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;

class SchoolUserDirectory
{
    public function find(int $userId): ?User
    {
        return User::find($userId);
    }

    public function createActive(string $name, string $email, string $password): User
    {
        return User::create(['name' => $name, 'email' => strtolower($email), 'password' => $password, 'status' => 'active']);
    }

    public function activeOptions()
    {
        return User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
    }
}
