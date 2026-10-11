<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });
        Schema::create('exams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_type_id');
            $table->foreignId('subject_id')->nullable();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('term_id')->nullable();
            $table->foreignId('assessment_component_id')->nullable();
            $table->foreignId('created_by');
            $table->string('title', 160);
            $table->unsignedSmallInteger('duration');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 20)->default('Draft');
            $table->decimal('pass_mark', 5, 2)->nullable();
            $table->string('result_release_type', 20);
            $table->timestamp('result_release_at')->nullable();
            $table->boolean('is_released')->default(false);
            $table->boolean('show_score')->default(false);
            $table->boolean('show_correct_answers')->default(false);
            $table->boolean('show_explanations')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('approved_for_release_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status', 'scheduled_at']);
            $table->foreign(['school_id', 'exam_type_id'], 'exam_type_fk')->references(['school_id', 'id'])->on('exam_types');
            $table->foreign(['school_id', 'subject_id'], 'exam_subject_fk')->references(['school_id', 'id'])->on('subjects');
            $table->foreign(['school_id', 'class_id'], 'exam_class_fk')->references(['school_id', 'id'])->on('classes');
            $table->foreign(['school_id', 'term_id'], 'exam_term_fk')->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'assessment_component_id'], 'exam_component_fk')->references(['school_id', 'id'])->on('assessment_components');
            $table->foreign(['school_id', 'created_by'], 'exam_creator_fk')->references(['school_id', 'id'])->on('users');
        });
        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_id');
            $table->text('text');
            $table->decimal('marks', 6, 2);
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'exam_id', 'position']);
            $table->foreign(['school_id', 'exam_id'], 'question_exam_fk')->references(['school_id', 'id'])->on('exams')->cascadeOnDelete();
        });
        Schema::create('question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('question_id');
            $table->string('text', 500);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'question_id']);
            $table->foreign(['school_id', 'question_id'], 'option_question_fk')->references(['school_id', 'id'])->on('questions')->cascadeOnDelete();
        });
        Schema::create('exam_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_id');
            $table->foreignId('candidate_id')->nullable();
            $table->foreignId('student_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('raw_score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2)->nullable();
            $table->decimal('converted_score', 7, 2)->nullable();
            $table->string('status', 20)->default('In Progress');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'exam_id', 'candidate_id'], 'exam_candidate_unique');
            $table->unique(['school_id', 'exam_id', 'student_id'], 'exam_student_unique');
            $table->foreign(['school_id', 'exam_id'], 'submission_exam_fk')->references(['school_id', 'id'])->on('exams');
            $table->foreign(['school_id', 'candidate_id'], 'submission_candidate_fk')->references(['school_id', 'id'])->on('candidates');
            $table->foreign(['school_id', 'student_id'], 'submission_student_fk')->references(['school_id', 'id'])->on('students');
        });
        Schema::create('exam_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_submission_id');
            $table->foreignId('question_id');
            $table->foreignId('selected_option_id');
            $table->timestamps();
            $table->unique(['school_id', 'exam_submission_id', 'question_id'], 'exam_answer_unique');
            $table->foreign(['school_id', 'exam_submission_id'], 'answer_submission_fk')->references(['school_id', 'id'])->on('exam_submissions')->cascadeOnDelete();
            $table->foreign(['school_id', 'question_id'], 'answer_question_fk')->references(['school_id', 'id'])->on('questions');
            $table->foreign(['school_id', 'selected_option_id'], 'answer_option_fk')->references(['school_id', 'id'])->on('question_options');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_submissions');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_types');
    }
};
