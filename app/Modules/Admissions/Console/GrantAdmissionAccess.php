<?php

namespace App\Modules\Admissions\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantAdmissionAccess extends Command
{
    protected $signature = 'oakfield:grant-admission-access';

    protected $description = 'Grant existing school administrators access to admissions';

    public function handle(TenantContext $context): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            $role = Role::where('name', 'School Admin')->first();
            if (! $role) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }
            $permission = Permission::firstOrCreate(['name' => 'manage_admissions']);
            $override = Permission::firstOrCreate(['name' => 'override_admission_checklist']);
            $role->permissions()->syncWithoutDetaching([$permission->id, $override->id]);
        } finally {
            $context->clear();
        }
        $this->info('Admission access granted.');

        return self::SUCCESS;
    }
}
