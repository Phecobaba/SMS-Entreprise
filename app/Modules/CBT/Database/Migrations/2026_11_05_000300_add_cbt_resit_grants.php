<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('student_id');
            $table->dropUnique('exam_student_unique');
            $table->unique(
                ['school_id', 'exam_id', 'student_id', 'attempt_number'],
                'exam_student_attempt_unique',
            );
        });

        Schema::create('exam_resit_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_id');
            $table->foreignId('student_id');
            $table->foreignId('granted_by');
            $table->string('reason', 500);
            $table->timestamp('opens_until');
            $table->foreignId('used_attempt_id')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'used_attempt_id'], 'resit_used_attempt_unique');
            $table->index(['school_id', 'exam_id', 'student_id', 'withdrawn_at'], 'resit_active_lookup');
            $table->foreign(['school_id', 'exam_id'], 'resit_exam_fk')->references(['school_id', 'id'])->on('exams');
            $table->foreign(['school_id', 'student_id'], 'resit_student_fk')->references(['school_id', 'id'])->on('students');
            $table->foreign(['school_id', 'granted_by'], 'resit_granter_fk')->references(['school_id', 'id'])->on('users');
            $table->foreign(['school_id', 'used_attempt_id'], 'resit_attempt_fk')->references(['school_id', 'id'])->on('exam_submissions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_resit_grants');

        Schema::table('exam_submissions', function (Blueprint $table): void {
            $table->dropUnique('exam_student_attempt_unique');
            $table->dropColumn('attempt_number');
            $table->unique(['school_id', 'exam_id', 'student_id'], 'exam_student_unique');
        });
    }
};
