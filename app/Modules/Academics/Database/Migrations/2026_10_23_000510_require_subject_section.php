<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('subjects')->whereNull('section_id')->count();
        if ($missing > 0) {
            throw new RuntimeException("Cannot require subjects.section_id: {$missing} subject(s) remain unassigned. Back up the database, run php artisan oakfield:migrate-subject-sections --dry-run, then run it with --actor=<user-id> before retrying this migration.");
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
            $table->unique(['school_id', 'section_id', 'name'], 'subject_section_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique('subject_section_name_unique');
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });
    }
};
