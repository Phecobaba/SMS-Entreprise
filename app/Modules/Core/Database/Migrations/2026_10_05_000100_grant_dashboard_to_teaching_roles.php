<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = DB::table('permissions')->whereIn('name', ['view_dashboard', 'mark_attendance', 'enter_results'])
            ->pluck('id', 'name');
        if (! $permissions->has('mark_attendance') || ! $permissions->has('enter_results')) {
            return;
        }
        $dashboardId = $permissions->get('view_dashboard')
            ?? DB::table('permissions')->insertGetId(['name' => 'view_dashboard', 'created_at' => now(), 'updated_at' => now()]);

        $roles = DB::table('roles')
            ->join('role_permissions as attendance', fn ($join) => $join->on('attendance.role_id', '=', 'roles.id')->on('attendance.school_id', '=', 'roles.school_id'))
            ->join('role_permissions as results', fn ($join) => $join->on('results.role_id', '=', 'roles.id')->on('results.school_id', '=', 'roles.school_id'))
            ->where('attendance.permission_id', $permissions->get('mark_attendance'))
            ->where('results.permission_id', $permissions->get('enter_results'))
            ->select('roles.id', 'roles.school_id')->distinct()->get();

        foreach ($roles as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'school_id' => $role->school_id, 'role_id' => $role->id,
                'permission_id' => $dashboardId,
            ]);
        }
    }

    public function down(): void
    {
        // Preserve permissions that may have been deliberately granted later.
    }
};
