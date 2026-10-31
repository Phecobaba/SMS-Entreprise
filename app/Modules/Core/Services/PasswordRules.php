<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordRules
{
    private const COMMON_PASSWORDS = [
        'password', 'password1', 'password123', '12345678', '123456789',
        'qwerty123', 'letmein1', 'welcome1', 'admin123', 'iloveyou',
    ];

    public function validate(
        string $password,
        ?string $username,
        string $schoolName,
        ?string $previousPasswordHash = null,
        bool $previousWasTemporary = false,
    ): void {
        if (mb_strlen($password) < 8) {
            $this->fail('Use at least 8 characters.');
        }

        $comparison = mb_strtolower(trim($password));
        if ($username && hash_equals(mb_strtolower(trim($username)), $comparison)) {
            $this->fail('Your password cannot be the same as your username.');
        }
        if ($schoolName !== '' && hash_equals($this->plain($schoolName), $this->plain($password))) {
            $this->fail('Your password cannot be the school name.');
        }
        if (preg_match('/^\d+$/', $password) === 1) {
            $this->fail('Your password cannot contain only numbers.');
        }
        if (in_array($comparison, self::COMMON_PASSWORDS, true)) {
            $this->fail('Choose a less common password.');
        }
        if ($previousPasswordHash && Hash::check($password, $previousPasswordHash)) {
            $this->fail($previousWasTemporary
                ? 'Choose a password different from the temporary password.'
                : 'Choose a password different from your current password.');
        }
    }

    private function plain(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($value)) ?? '';
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['password' => $message]);
    }
}
