<?php

namespace App\Modules\CBT\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantCbtAccess extends Command
{
    protected $signature = 'oakfield:grant-cbt-access';

    protected $description = 'Grant existing school administrators CBT authoring and release approval access';

    public function handle(TenantContext $context): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            $role = Role::where('name', 'School Admin')->first();
            if (! $role) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }
            $ids = [];
            foreach (['manage_cbt', 'approve_cbt_results'] as $name) {
                $ids[] = Permission::firstOrCreate(['name' => $name])->id;
            }
            $role->permissions()->syncWithoutDetaching($ids);
        } finally {
            $context->clear();
        }

        $this->info('CBT access granted to School Admin.');

        return self::SUCCESS;
    }
}
