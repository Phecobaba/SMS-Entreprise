<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\DefaultRoleCatalog;
use App\Modules\Core\Services\PasswordRules;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BootstrapSchoolAdmin extends Command
{
    protected $signature = 'oakfield:bootstrap-admin {school-name} {admin-name} {email}';

    protected $description = 'Create the configured school and its first administrator';

    public function handle(TenantContext $context, DefaultRoleCatalog $roles): int
    {
        if (config('tenancy.mode') !== 'single_school') {
            $this->error('This command only supports single-school mode.');

            return self::FAILURE;
        }

        $schoolId = (int) config('tenancy.default_school_id');
        $email = strtolower((string) $this->argument('email'));

        if ($schoolId < 1 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid configured school ID and administrator email.');

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (at least 8 characters)');

        if (! is_string($password)) {
            $this->error('Enter an administrator password.');

            return self::FAILURE;
        }

        try {
            app(PasswordRules::class)->validate($password, null, (string) $this->argument('school-name'));
        } catch (ValidationException $exception) {
            $this->error($exception->errors()['password'][0] ?? 'Choose a different password.');

            return self::FAILURE;
        }

        $context->set($schoolId);

        try {
            DB::transaction(function () use ($schoolId, $email, $password, $roles): void {
                $school = School::find($schoolId);

                if (! $school) {
                    $school = new School;
                    $school->id = $schoolId;
                    $school->name = (string) $this->argument('school-name');
                    $school->save();
                }

                SchoolSetting::firstOrCreate([]);
                $role = $roles->provision()['School Admin'];

                $user = User::firstOrCreate(
                    ['email' => $email],
                    ['name' => (string) $this->argument('admin-name'), 'email_verified_at' => now(), 'password' => $password, 'status' => 'active'],
                );

                if ($user->status !== 'active') {
                    throw new RuntimeException('The existing administrator account is inactive.');
                }

                $user->roles()->syncWithoutDetaching([$role->id]);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $context->clear();
        }

        $this->info('School administrator is ready to sign in.');

        return self::SUCCESS;
    }
}
