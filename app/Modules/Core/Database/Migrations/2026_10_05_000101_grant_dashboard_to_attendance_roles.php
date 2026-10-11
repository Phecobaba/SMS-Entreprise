<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $markId = DB::table('permissions')->where('name', 'mark_attendance')->value('id');
        if (! $markId) {
            return;
        }
        $dashboardId = DB::table('permissions')->where('name', 'view_dashboard')->value('id')
            ?? DB::table('permissions')->insertGetId(['name' => 'view_dashboard', 'created_at' => now(), 'updated_at' => now()]);

        foreach (DB::table('role_permissions')->where('permission_id', $markId)->get(['role_id', 'school_id']) as $assignment) {
            DB::table('role_permissions')->insertOrIgnore([
                'school_id' => $assignment->school_id,
                'role_id' => $assignment->role_id,
                'permission_id' => $dashboardId,
            ]);
        }
    }

    public function down(): void
    {
        // Preserve later permission grants.
    }
};
