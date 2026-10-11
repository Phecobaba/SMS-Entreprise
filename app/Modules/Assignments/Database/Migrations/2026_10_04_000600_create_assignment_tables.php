<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'user_id']);
            $table->foreign(['school_id', 'user_id'])->references(['school_id', 'id'])->on('users');
        });
        Schema::create('parent_student', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('parent_id');
            $table->foreignId('student_id');
            $table->timestamps();
            $table->unique(['school_id', 'parent_id', 'student_id']);
            $table->index(['school_id', 'student_id']);
            $table->foreign(['school_id', 'parent_id'])->references(['school_id', 'id'])->on('parents');
            $table->foreign(['school_id', 'student_id'])->references(['school_id', 'id'])->on('students');
        });
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('subject_id');
            $table->foreignId('class_arm_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'class_arm_id', 'due_date']);
            $table->foreign(['school_id', 'subject_id'])->references(['school_id', 'id'])->on('subjects');
            $table->foreign(['school_id', 'class_arm_id'])->references(['school_id', 'id'])->on('class_arms');
            $table->foreign(['school_id', 'created_by'])->references(['school_id', 'id'])->on('users');
        });
        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('assignment_id');
            $table->foreignId('student_id');
            $table->string('status', 20)->default('Not Submitted');
            $table->foreignId('marked_by')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'assignment_id', 'student_id'], 'assignment_student_unique');
            $table->index(['school_id', 'student_id']);
            $table->foreign(['school_id', 'assignment_id'], 'assignment_submission_assignment_fk')->references(['school_id', 'id'])->on('assignments');
            $table->foreign(['school_id', 'student_id'], 'assignment_submission_student_fk')->references(['school_id', 'id'])->on('students');
            $table->foreign(['school_id', 'marked_by'], 'assignment_submission_marker_fk')->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('parents');
    }
};
