<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->string('principal_comment_mode', 20)->default('single')->after('head_title');
            $table->string('proprietor_comment_mode', 20)->default('single')->after('principal_comment_mode');
        });

        Schema::create('comment_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->string('comment_type', 20);
            $table->unsignedTinyInteger('min_average');
            $table->unsignedTinyInteger('max_average');
            $table->string('comment_text', 320);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'section_id', 'comment_type']);
            $table->foreign(['school_id', 'section_id'], 'comment_range_section_fk')
                ->references(['school_id', 'id'])->on('sections')->cascadeOnDelete();
        });

        Schema::table('result_templates', function (Blueprint $table): void {
            $table->boolean('show_overall_total')->default(true)->after('show_subject_remarks');
            $table->boolean('show_average')->default(true)->after('show_overall_total');
            $table->boolean('show_position')->default(true)->after('show_average');
        });

        Schema::table('school_settings', function (Blueprint $table): void {
            $table->string('proprietor_comment_title', 100)->default("Proprietor's comment")->after('motto');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->dropColumn('proprietor_comment_title');
        });
        Schema::table('result_templates', function (Blueprint $table): void {
            $table->dropColumn(['show_overall_total', 'show_average', 'show_position']);
        });
        Schema::dropIfExists('comment_ranges');
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn(['principal_comment_mode', 'proprietor_comment_mode']);
        });
    }
};
