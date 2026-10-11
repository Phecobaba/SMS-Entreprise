<?php

namespace App\Modules\Results\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantResultAccess extends Command
{
    protected $signature = 'oakfield:grant-result-access';

    protected $description = 'Grant existing school administrators Results setup, entry and approval access';

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
            foreach (['manage_result_settings', 'enter_results', 'approve_results', 'add_principal_comment', 'add_proprietor_comment'] as $name) {
                $ids[] = Permission::firstOrCreate(['name' => $name])->id;
            }
            $role->permissions()->syncWithoutDetaching($ids);
        } finally {
            $context->clear();
        }

        $this->info('Results access granted to School Admin.');

        return self::SUCCESS;
    }
}
