<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->foreignId('class_arm_id');
            $table->date('date');
            $table->string('status', 20);
            $table->foreignId('marked_by');
            $table->boolean('locked')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'student_id', 'date'], 'attendance_student_date_unique');
            $table->index(['school_id', 'class_arm_id', 'date'], 'attendance_arm_date_index');
            $table->foreign(['school_id', 'student_id'], 'attendance_student_fk')->references(['school_id', 'id'])->on('students');
            $table->foreign(['school_id', 'class_arm_id'], 'attendance_arm_fk')->references(['school_id', 'id'])->on('class_arms');
            $table->foreign(['school_id', 'marked_by'], 'attendance_marker_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('attendance_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_arm_id');
            $table->date('date');
            $table->boolean('resolved')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'class_arm_id', 'date'], 'attendance_alert_arm_date_unique');
            $table->foreign(['school_id', 'class_arm_id'], 'attendance_alert_arm_fk')->references(['school_id', 'id'])->on('class_arms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_alerts');
        Schema::dropIfExists('attendance_records');
    }
};
