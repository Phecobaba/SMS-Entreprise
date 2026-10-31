<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_templates', function (Blueprint $table): void {
            $table->string('active_scope_key', 40)->nullable()->after('scope_key');
            $table->timestamp('retired_at')->nullable()->after('show_subject_remarks');
            $table->unsignedBigInteger('retired_by')->nullable()->after('retired_at');
            $table->index(['school_id', 'retired_at'], 'result_template_retired_index');
            $table->foreign(['school_id', 'retired_by'], 'result_template_retired_by_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        DB::table('result_templates')->update(['active_scope_key' => DB::raw('scope_key')]);

        Schema::table('result_templates', function (Blueprint $table): void {
            $table->dropUnique('result_templates_school_id_scope_key_unique');
            $table->unique(['school_id', 'active_scope_key'], 'result_template_active_scope_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('result_templates')->whereNotNull('retired_at')->exists()) {
            throw new RuntimeException('Cannot remove Result-template retirement while retired templates exist.');
        }

        Schema::table('result_templates', function (Blueprint $table): void {
            $table->dropUnique('result_template_active_scope_unique');
            $table->unique(['school_id', 'scope_key']);
            $table->dropForeign('result_template_retired_by_fk');
            $table->dropIndex('result_template_retired_index');
            $table->dropColumn(['active_scope_key', 'retired_at', 'retired_by']);
        });
    }
};
