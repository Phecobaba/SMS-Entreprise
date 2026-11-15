<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('module', 50)->nullable()->after('actor_role');
            $table->text('description')->nullable()->after('action');
            $table->index(['school_id', 'module', 'created_at'], 'audit_log_module_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_log_module_created_index');
            $table->dropColumn(['module', 'description']);
        });
    }
};
