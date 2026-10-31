<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_schemes', function (Blueprint $table): void {
            $table->dropUnique('grading_schemes_school_id_name_unique');
            $table->foreignId('section_id')->nullable()->after('school_id');
            $table->index(['school_id', 'section_id'], 'grading_scheme_section_index');
            $table->foreign(['school_id', 'section_id'], 'grading_scheme_section_fk')
                ->references(['school_id', 'id'])->on('sections');
        });

        Schema::table('assessment_components', function (Blueprint $table): void {
            $table->dropUnique('assessment_components_school_id_name_unique');
            $table->foreignId('section_id')->nullable()->after('school_id');
            $table->index(['school_id', 'section_id'], 'assessment_component_section_index');
            $table->foreign(['school_id', 'section_id'], 'assessment_component_section_fk')
                ->references(['school_id', 'id'])->on('sections');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table): void {
            $table->dropForeign('assessment_component_section_fk');
            $table->dropIndex('assessment_component_section_index');
            $table->dropColumn('section_id');
            $table->unique(['school_id', 'name']);
        });

        Schema::table('grading_schemes', function (Blueprint $table): void {
            $table->dropForeign('grading_scheme_section_fk');
            $table->dropIndex('grading_scheme_section_index');
            $table->dropColumn('section_id');
            $table->unique(['school_id', 'name']);
        });
    }
};
