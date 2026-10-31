<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique('subjects_school_id_name_unique');
            $table->foreignId('section_id')->nullable()->after('school_id');
            $table->index(['school_id', 'section_id'], 'subject_section_index');
            $table->foreign(['school_id', 'section_id'], 'subject_section_fk')
                ->references(['school_id', 'id'])->on('sections');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropForeign('subject_section_fk');
            $table->dropIndex('subject_section_index');
            $table->dropColumn('section_id');
            $table->unique(['school_id', 'name']);
        });
    }
};
