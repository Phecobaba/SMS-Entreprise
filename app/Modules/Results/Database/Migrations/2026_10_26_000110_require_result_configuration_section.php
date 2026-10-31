<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingScales = DB::table('grading_schemes')->whereNull('section_id')->count();
        $missingComponents = DB::table('assessment_components')->whereNull('section_id')->count();
        if ($missingScales > 0 || $missingComponents > 0) {
            throw new RuntimeException("Cannot require result configuration Sections: {$missingScales} scale(s) and {$missingComponents} component(s) remain unassigned. Back up the database, run php artisan oakfield:migrate-result-sections --dry-run, then run it with --actor=<user-id> before retrying this migration.");
        }

        Schema::table('grading_schemes', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
            $table->unique(['school_id', 'section_id', 'name'], 'grading_scheme_section_name_unique');
        });
        Schema::table('assessment_components', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
            $table->unique(['school_id', 'section_id', 'name'], 'assessment_component_section_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table): void {
            $table->dropUnique('assessment_component_section_name_unique');
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });
        Schema::table('grading_schemes', function (Blueprint $table): void {
            $table->dropUnique('grading_scheme_section_name_unique');
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });
    }
};
