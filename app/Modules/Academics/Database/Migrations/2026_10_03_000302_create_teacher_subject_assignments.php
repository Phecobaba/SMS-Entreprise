<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_subject_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->foreignId('class_id');
            $table->foreignId('subject_id');
            $table->timestamps();
            $table->unique(['school_id', 'user_id', 'class_id', 'subject_id'], 'teacher_assignment_unique');
            $table->index(['school_id', 'class_id', 'subject_id'], 'teacher_assignment_subject_index');
            $table->foreign(['school_id', 'user_id'], 'teacher_assignment_user_fk')->references(['school_id', 'id'])->on('users');
            $table->foreign(['school_id', 'class_id', 'subject_id'], 'teacher_assignment_class_subject_fk')->references(['school_id', 'class_id', 'subject_id'])->on('class_subjects');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_assignments');
    }
};
