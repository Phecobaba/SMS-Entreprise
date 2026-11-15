<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_level', function (Blueprint $table): void {
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_id');
            $table->foreignId('level_id');
            $table->primary(['exam_id', 'level_id']);
            $table->index(['school_id', 'level_id']);
            $table->foreign(['school_id', 'exam_id'], 'exam_level_exam_fk')
                ->references(['school_id', 'id'])->on('exams')->cascadeOnDelete();
            $table->foreign(['school_id', 'level_id'], 'exam_level_level_fk')
                ->references(['school_id', 'id'])->on('academic_levels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_level');
    }
};
