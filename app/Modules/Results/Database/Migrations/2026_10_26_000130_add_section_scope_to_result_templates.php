<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        Schema::table('result_templates', function (Blueprint $table) use ($sqlite): void {
            $table->foreignId('section_id')->nullable()->after('school_id');
            $table->index(['school_id', 'section_id'], 'result_template_section_index');
            $table->foreign(['school_id', 'section_id'], 'result_template_section_fk')
                ->references(['school_id', 'id'])->on('sections');
            $table->dropForeign($sqlite ? ['school_id', 'academic_level_id'] : 'result_template_level_fk');
            $table->unsignedBigInteger('academic_level_id')->nullable()->change();
            $table->foreign(['school_id', 'academic_level_id'], 'result_template_level_fk')
                ->references(['school_id', 'id'])->on('academic_levels');
        });

        DB::table('result_templates')->orderBy('id')->each(function (object $template): void {
            $sectionId = DB::table('academic_levels')
                ->where('school_id', $template->school_id)
                ->where('id', $template->academic_level_id)
                ->value('section_id');
            if ($sectionId === null) {
                throw new RuntimeException("Cannot backfill Result template {$template->id}: its Level has no Section.");
            }
            DB::table('result_templates')->where('id', $template->id)->update(['section_id' => $sectionId]);
        });
    }

    public function down(): void
    {
        if (DB::table('result_templates')->whereNull('academic_level_id')->exists()) {
            throw new RuntimeException('Cannot remove section-wide Result-template support while a section-wide template exists.');
        }

        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        Schema::table('result_templates', function (Blueprint $table) use ($sqlite): void {
            $table->dropForeign($sqlite ? ['school_id', 'academic_level_id'] : 'result_template_level_fk');
            $table->unsignedBigInteger('academic_level_id')->nullable(false)->change();
            $table->foreign(['school_id', 'academic_level_id'], 'result_template_level_fk')
                ->references(['school_id', 'id'])->on('academic_levels');
            $table->dropForeign($sqlite ? ['school_id', 'section_id'] : 'result_template_section_fk');
            $table->dropIndex('result_template_section_index');
            $table->dropColumn('section_id');
        });
    }
};
