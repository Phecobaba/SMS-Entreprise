<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('status', 24)->default('Active')->after('candidate_id');
            $table->foreignId('awaiting_from_class_arm_id')->nullable()->after('status');
            $table->timestamp('awaiting_admission_at')->nullable()->after('awaiting_from_class_arm_id');
            $table->index(['school_id', 'status', 'class_arm_id'], 'student_status_arm_idx');
            $table->index(['school_id', 'status', 'awaiting_admission_at'], 'student_waiting_idx');
            $table->foreign(['school_id', 'awaiting_from_class_arm_id'], 'student_waiting_arm_fk')
                ->references(['school_id', 'id'])->on('class_arms');
        });

        DB::table('students')->whereNull('status')->update(['status' => 'Active']);

        $permissionId = DB::table('permissions')->where('name', 'manage_promotions')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'manage_promotions',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roles')->whereIn('name', ['School Admin', 'Principal'])->get(['id', 'school_id']) as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'school_id' => $role->school_id,
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage_promotions')->value('id');
        if ($permissionId) {
            $roleIds = DB::table('roles')->whereIn('name', ['School Admin', 'Principal'])->pluck('id');
            DB::table('role_permissions')->where('permission_id', $permissionId)->whereIn('role_id', $roleIds)->delete();
        }

        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign('student_waiting_arm_fk');
            $table->dropIndex('student_status_arm_idx');
            $table->dropIndex('student_waiting_idx');
            $table->dropColumn(['status', 'awaiting_from_class_arm_id', 'awaiting_admission_at']);
        });
    }
};
