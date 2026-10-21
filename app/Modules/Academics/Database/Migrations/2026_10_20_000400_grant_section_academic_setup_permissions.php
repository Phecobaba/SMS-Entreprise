<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['manage_section_levels', 'manage_section_arms'] as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id')
                ?: DB::table('permissions')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);

            foreach (DB::table('roles')->where('name', 'Principal')->get(['id', 'school_id']) as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'school_id' => $role->school_id,
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permissions may have been assigned deliberately after deployment.
    }
};
