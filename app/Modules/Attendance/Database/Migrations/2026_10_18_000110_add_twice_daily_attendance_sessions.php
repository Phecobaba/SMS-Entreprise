<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique('attendance_student_date_unique');
            $table->string('session', 20)->nullable()->after('date');
            $table->unique(['school_id', 'student_id', 'date', 'session'], 'attendance_student_date_session_unique');
        });

        Schema::table('attendance_alerts', function (Blueprint $table): void {
            $table->dropUnique('attendance_alert_arm_date_unique');
            $table->string('session', 20)->nullable()->after('date');
            $table->unique(['school_id', 'class_arm_id', 'date', 'session'], 'attendance_alert_arm_date_session_unique');
        });

        Schema::create('attendance_alert_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->time('morning_due_at')->default('08:00');
            $table->time('afternoon_due_at')->default('14:00');
            $table->foreignId('updated_by');
            $table->timestamps();
            $table->unique(['school_id', 'section_id']);
            $table->foreign(['school_id', 'section_id'], 'attendance_setting_section_fk')
                ->references(['school_id', 'id'])->on('sections');
            $table->foreign(['school_id', 'updated_by'], 'attendance_setting_user_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        Schema::create('attendance_sessions_not_held', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->date('date');
            $table->string('session', 20);
            $table->foreignId('marked_by');
            $table->timestamp('created_at');
            $table->unique(['school_id', 'section_id', 'date', 'session'], 'attendance_not_held_unique');
            $table->foreign(['school_id', 'section_id'], 'attendance_not_held_section_fk')
                ->references(['school_id', 'id'])->on('sections');
            $table->foreign(['school_id', 'marked_by'], 'attendance_not_held_user_fk')
                ->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions_not_held');
        Schema::dropIfExists('attendance_alert_settings');

        Schema::table('attendance_alerts', function (Blueprint $table): void {
            $table->dropUnique('attendance_alert_arm_date_session_unique');
            $table->dropColumn('session');
            $table->unique(['school_id', 'class_arm_id', 'date'], 'attendance_alert_arm_date_unique');
        });
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropUnique('attendance_student_date_session_unique');
            $table->dropColumn('session');
            $table->unique(['school_id', 'student_id', 'date'], 'attendance_student_date_unique');
        });
    }
};
