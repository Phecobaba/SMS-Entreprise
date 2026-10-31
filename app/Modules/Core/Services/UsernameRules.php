<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UsernameRules
{
    public function normalize(string $username): string
    {
        return strtolower(trim($username));
    }

    public function formatError(string $username): ?string
    {
        $username = $this->normalize($username);
        if (strlen($username) < 3 || strlen($username) > 30) {
            return 'Use 3 to 30 characters.';
        }
        if (str_contains($username, '@')) {
            return 'A username cannot contain @.';
        }
        if (! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $username)) {
            return 'Use lower-case letters, numbers, dots, dashes or underscores, starting with a letter or number.';
        }

        return null;
    }

    public function isTaken(string $username, ?int $exceptUserId = null): bool
    {
        $query = User::query()->whereRaw('LOWER(username) = ?', [$this->normalize($username)]);
        if ($exceptUserId) {
            $query->whereKeyNot($exceptUserId);
        }

        return $query->exists();
    }

    public function validateAvailable(string $username, ?int $exceptUserId = null): string
    {
        $normalized = $this->normalize($username);
        if ($error = $this->formatError($normalized)) {
            throw ValidationException::withMessages(['username' => $error]);
        }
        if ($this->isTaken($normalized, $exceptUserId)) {
            throw ValidationException::withMessages([
                'username' => 'That username is already in use. Try '.$this->suggestions($normalized, 2)->join(' or ').'.',
            ]);
        }

        return $normalized;
    }

    public function suggestions(string $base, int $count = 2): Collection
    {
        $base = preg_replace('/[^a-z0-9._-]+/', '.', $this->normalize($base)) ?: 'staff';
        $base = trim($base, '.-_');
        $base = substr($base, 0, 26);
        $suggestions = collect();
        for ($number = 2; $suggestions->count() < $count && $number < 1000; $number++) {
            $candidate = substr($base, 0, 30 - strlen((string) $number)).$number;
            if (! $this->isTaken($candidate)) {
                $suggestions->push($candidate);
            }
        }

        return $suggestions;
    }
}
