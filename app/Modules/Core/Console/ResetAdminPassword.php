<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\SessionRevoker;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAdminPassword extends Command
{
    protected $signature = 'oakfield:reset-admin-password {identifier}';

    protected $description = 'Issue a one-time temporary password for an Admin account';

    public function handle(TenantContext $context): int
    {
        $schoolId = (int) config('tenancy.default_school_id');
        if ($schoolId < 1 || ! School::withoutGlobalScopes()->whereKey($schoolId)->exists()) {
            $this->error('The configured school does not exist.');

            return self::FAILURE;
        }

        $context->set($schoolId);
        try {
            $identifier = mb_strtolower(trim((string) $this->argument('identifier')));
            $column = str_contains($identifier, '@') ? 'email' : 'username';
            $user = User::whereRaw('LOWER('.$column.') = ?', [$identifier])->first();

            if (! $user || ! $user->can('view_all_sections')) {
                $this->error('The account is not an Admin account for this school.');

                return self::FAILURE;
            }

            $temporaryPassword = Str::password(length: 12, symbols: false);
            $user->setRawAttributes(array_merge($user->getAttributes(), [
                'password' => Hash::make($temporaryPassword, ['rounds' => config('accounts.temporary_bcrypt_rounds')]),
                'must_change_password' => true,
                'remember_token' => null,
            ]));
            $user->save();
            app(SessionRevoker::class)->all($user);
            AuditLog::create([
                // audit_logs.user_id is required; retain the affected account id
                // while the actor snapshot makes the console origin explicit.
                'user_id' => $user->id,
                'actor_name' => 'Console break-glass',
                'actor_role' => 'Console',
                'action' => 'admin_password_break_glass_reset',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => null,
                'created_at' => now(),
            ]);

            $this->warn('Temporary password (shown once): '.$temporaryPassword);
            $this->info('The Admin must choose a new password at the next sign-in.');

            return self::SUCCESS;
        } finally {
            $context->clear();
        }
    }
}
