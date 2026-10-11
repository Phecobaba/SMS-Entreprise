<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('academic_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('session_id');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->unique(['school_id', 'session_id', 'name']);
            $table->index(['school_id', 'session_id']);
            $table->foreign(['school_id', 'session_id'])->references(['school_id', 'id'])->on('academic_sessions')->cascadeOnDelete();
        });

        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_level_id');
            $table->string('name');
            $table->timestamps();
            $table->unique(['school_id', 'academic_level_id', 'name']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'academic_level_id']);
            $table->foreign(['school_id', 'academic_level_id'])->references(['school_id', 'id'])->on('academic_levels')->cascadeOnDelete();
        });

        Schema::create('class_arms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_id');
            $table->string('name');
            $table->timestamps();
            $table->unique(['school_id', 'class_id', 'name']);
            $table->index(['school_id', 'class_id']);
            $table->foreign(['school_id', 'class_id'])->references(['school_id', 'id'])->on('classes')->cascadeOnDelete();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('class_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_id');
            $table->foreignId('subject_id');
            $table->timestamps();
            $table->unique(['school_id', 'class_id', 'subject_id']);
            $table->index(['school_id', 'class_id']);
            $table->index(['school_id', 'subject_id']);
            $table->foreign(['school_id', 'class_id'])->references(['school_id', 'id'])->on('classes')->cascadeOnDelete();
            $table->foreign(['school_id', 'subject_id'])->references(['school_id', 'id'])->on('subjects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_arms');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_sessions');
        Schema::dropIfExists('academic_levels');
    }
};
