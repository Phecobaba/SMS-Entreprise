<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TemporaryCredentials
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function generate(): string
    {
        $value = '';
        for ($index = 0; $index < 12; $index++) {
            $value .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $value;
    }

    public function hash(string $password): string
    {
        return Hash::make($password, ['rounds' => config('accounts.temporary_bcrypt_rounds')]);
    }

    public function reset(User $target, User $actor, string $auditAction): array
    {
        $temporary = $this->generate();
        DB::transaction(function () use ($target, $actor, $auditAction, $temporary): void {
            $target->setRawAttributes(array_merge($target->getAttributes(), [
                'password' => $this->hash($temporary),
                'must_change_password' => true,
            ]));
            $target->save();
            app(SessionRevoker::class)->all($target);
            app(AuditRecorder::class)->record($actor, $auditAction, $target);
        });

        return ['user' => $target->refresh(), 'temporary_password' => $temporary];
    }
}
