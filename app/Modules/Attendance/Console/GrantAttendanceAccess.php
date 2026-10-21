<?php

namespace App\Modules\Attendance\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GrantAttendanceAccess extends Command
{
    protected $signature = 'oakfield:grant-attendance-access';

    protected $description = 'Grant Admin management, Teacher marking, and Section-scoped Principal attendance management';

    public function handle(TenantContext $context): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            $admin = Role::where('name', 'School Admin')->first();
            if (! $admin) {
                $this->error('Bootstrap the school administrator first.');

                return self::FAILURE;
            }
            $mark = Permission::firstOrCreate(['name' => 'mark_attendance']);
            $manage = Permission::firstOrCreate(['name' => 'manage_attendance']);
            $alerts = Permission::firstOrCreate(['name' => 'view_attendance_alerts']);
            $admin->permissions()->syncWithoutDetaching([$mark->id, $manage->id, $alerts->id]);
            Role::firstOrCreate(['name' => 'Teacher'])->permissions()->syncWithoutDetaching([$mark->id]);
            Role::firstOrCreate(['name' => 'Principal'])->permissions()->syncWithoutDetaching([$manage->id, $alerts->id]);
        } finally {
            $context->clear();
        }

        $this->info('Attendance permissions granted to School Admin, Teacher, and Principal.');

        return self::SUCCESS;
    }
}
