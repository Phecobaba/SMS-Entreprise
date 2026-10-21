<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage_attendance')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'manage_attendance', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (DB::table('roles')->where('name', 'Principal')->get(['id', 'school_id']) as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'school_id' => $role->school_id,
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage_attendance')->value('id');
        if ($permissionId) {
            $principalIds = DB::table('roles')->where('name', 'Principal')->pluck('id');
            DB::table('role_permissions')->where('permission_id', $permissionId)->whereIn('role_id', $principalIds)->delete();
        }
    }
};
