<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'manage_section_subjects',
        'manage_section_teacher_assignments',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
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
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');
        $principalIds = DB::table('roles')->where('name', 'Principal')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->whereIn('role_id', $principalIds)->delete();
    }
};
