<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->foreignId('term_id');
            $table->foreignId('class_arm_id');
            $table->string('class_teacher_comment', 320)->nullable();
            $table->string('principal_comment', 320)->nullable();
            $table->string('proprietor_comment', 320)->nullable();
            $table->date('next_term_begins')->nullable();
            $table->timestamp('compiled_at');
            $table->timestamps();
            $table->unique(['school_id', 'student_id', 'term_id']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'class_arm_id', 'term_id']);
            $table->foreign(['school_id', 'student_id'], 'card_student_fk')->references(['school_id', 'id'])->on('students');
            $table->foreign(['school_id', 'term_id'], 'card_term_fk')->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'class_arm_id'], 'card_arm_fk')->references(['school_id', 'id'])->on('class_arms');
        });

        Schema::create('term_default_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('term_id');
            $table->string('comment_type', 20);
            $table->string('comment_text', 320);
            $table->timestamps();
            $table->unique(['school_id', 'term_id', 'comment_type'], 'term_default_comment_unique');
            $table->foreign(['school_id', 'term_id'], 'term_default_term_fk')->references(['school_id', 'id'])->on('terms');
        });

        Schema::create('result_card_comment_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('result_card_id')->nullable();
            $table->foreignId('term_id');
            $table->foreignId('user_id');
            $table->string('comment_type', 20);
            $table->string('scope', 20);
            $table->string('comment_text', 320)->nullable();
            $table->timestamp('created_at');
            $table->index(['school_id', 'term_id', 'created_at']);
            $table->foreign(['school_id', 'result_card_id'], 'card_comment_card_fk')->references(['school_id', 'id'])->on('result_cards')->cascadeOnDelete();
            $table->foreign(['school_id', 'term_id'], 'card_comment_term_fk')->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'user_id'], 'card_comment_user_fk')->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_card_comment_audit');
        Schema::dropIfExists('term_default_comments');
        Schema::dropIfExists('result_cards');
    }
};
