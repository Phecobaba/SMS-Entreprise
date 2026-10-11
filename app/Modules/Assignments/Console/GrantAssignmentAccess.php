<?php

namespace App\Modules\Assignments\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantAssignmentAccess extends Command
{
    protected $signature = 'oakfield:grant-assignment-access';

    protected $description = 'Grant assignment permissions to existing school roles';

    public function handle(TenantContext $context): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            $admin = Role::where('name', 'School Admin')->first();
            if (! $admin) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }
            $manage = Permission::firstOrCreate(['name' => 'manage_assignments']);
            $mark = Permission::firstOrCreate(['name' => 'mark_assignments']);
            $admin->permissions()->syncWithoutDetaching([$manage->id, $mark->id]);
            Role::firstOrCreate(['name' => 'Teacher'])->permissions()->syncWithoutDetaching([$mark->id]);
        } finally {
            $context->clear();
        }

        $this->info('Assignment permissions granted.');

        return self::SUCCESS;
    }
}
