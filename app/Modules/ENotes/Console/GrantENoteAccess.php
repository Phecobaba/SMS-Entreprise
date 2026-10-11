<?php

namespace App\Modules\ENotes\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantENoteAccess extends Command
{
    protected $signature = 'oakfield:grant-enote-access';

    protected $description = 'Grant E-Note upload access to School Admin and Teacher roles';

    public function handle(TenantContext $context): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            $admin = Role::where('name', 'School Admin')->first();
            if (! $admin) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }
            $manage = Permission::firstOrCreate(['name' => 'manage_enotes']);
            $upload = Permission::firstOrCreate(['name' => 'upload_enotes']);
            $admin->permissions()->syncWithoutDetaching([$manage->id, $upload->id]);
            Role::firstOrCreate(['name' => 'Teacher'])->permissions()->syncWithoutDetaching([$upload->id]);
        } finally {
            $context->clear();
        }

        $this->info('E-Note permissions granted.');

        return self::SUCCESS;
    }
}
