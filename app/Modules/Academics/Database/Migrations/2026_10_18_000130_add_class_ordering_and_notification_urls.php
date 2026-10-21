<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->unsignedInteger('position')->nullable()->after('name');
            $table->index(['school_id', 'position'], 'class_school_position_idx');
        });

        Schema::table('sections', function (Blueprint $table): void {
            $table->foreignId('classes_arranged_by')->nullable()->after('head_title');
            $table->timestamp('classes_arranged_at')->nullable()->after('classes_arranged_by');
            $table->index(['school_id', 'classes_arranged_at'], 'section_arranged_idx');
            $table->foreign(['school_id', 'classes_arranged_by'], 'section_arranger_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable()->after('body');
        });

        $permissionId = DB::table('permissions')->where('name', 'manage_section_classes')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'manage_section_classes',
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

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage_section_classes')->value('id');
        if ($permissionId) {
            $principalIds = DB::table('roles')->where('name', 'Principal')->pluck('id');
            DB::table('role_permissions')->where('permission_id', $permissionId)->whereIn('role_id', $principalIds)->delete();
        }

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('url');
        });
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropForeign('section_arranger_fk');
            $table->dropIndex('section_arranged_idx');
            $table->dropColumn(['classes_arranged_by', 'classes_arranged_at']);
        });
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropIndex('class_school_position_idx');
            $table->dropColumn('position');
        });
    }
};
