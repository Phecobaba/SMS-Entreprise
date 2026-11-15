<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 150);
            $table->dateTime('starts_at');
            $table->string('venue_note', 500);
            $table->boolean('is_in_person')->default(true);
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'starts_at']);
            $table->index(['school_id', 'section_id', 'starts_at']);
            $table->foreign(['school_id', 'section_id'], 'exam_session_section_fk')
                ->references(['school_id', 'id'])->on('sections')->restrictOnDelete();
            $table->foreign(['school_id', 'created_by'], 'exam_session_creator_fk')
                ->references(['school_id', 'id'])->on('users')->restrictOnDelete();
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('exam_session_id')->nullable();
            $table->timestamp('exam_notified_at')->nullable();
            $table->index(['school_id', 'exam_session_id']);
            $table->foreign(['school_id', 'exam_session_id'], 'application_exam_session_fk')
                ->references(['school_id', 'id'])->on('exam_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign('application_exam_session_fk');
            $table->dropIndex(['school_id', 'exam_session_id']);
            $table->dropColumn(['exam_session_id', 'exam_notified_at']);
        });
        Schema::dropIfExists('exam_sessions');
    }
};
