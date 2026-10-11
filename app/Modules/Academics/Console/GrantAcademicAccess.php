<?php

namespace App\Modules\Academics\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantAcademicAccess extends Command
{
    protected $signature = 'oakfield:grant-academic-access';

    protected $description = 'Grant existing School Admin roles access to Academic configuration';

    public function handle(TenantContext $context): int
    {
        if (config('tenancy.mode') !== 'single_school') {
            $this->error('This command only supports single-school mode.');

            return self::FAILURE;
        }

        $context->set((int) config('tenancy.default_school_id'));

        try {
            $permission = Permission::firstOrCreate(['name' => 'manage_academics']);
            $role = Role::where('name', 'School Admin')->first();

            if (! $role) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }

            $role->permissions()->syncWithoutDetaching([$permission->id]);
        } finally {
            $context->clear();
        }

        $this->info('Academic configuration access granted to School Admin.');

        return self::SUCCESS;
    }
}
