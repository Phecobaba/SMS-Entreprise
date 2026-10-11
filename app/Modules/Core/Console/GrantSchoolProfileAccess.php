<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantSchoolProfileAccess extends Command
{
    protected $signature = 'oakfield:grant-school-profile-access';

    protected $description = 'Grant an existing School Admin access to the school profile settings';

    public function handle(TenantContext $context): int
    {
        if (config('tenancy.mode') !== 'single_school') {
            $this->error('This command only supports single-school mode.');

            return self::FAILURE;
        }

        $context->set((int) config('tenancy.default_school_id'));

        try {
            $role = Role::where('name', 'School Admin')->first();
            if (! $role) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }

            $permission = Permission::firstOrCreate(['name' => 'manage_school_profile']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        } finally {
            $context->clear();
        }

        $this->info('School profile access granted to School Admin.');

        return self::SUCCESS;
    }
}
