<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('subject_id');
            $table->foreignId('class_id');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('link', 2048)->nullable();
            $table->foreignId('uploaded_by');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'class_id', 'subject_id']);
            $table->foreign(['school_id', 'subject_id'])->references(['school_id', 'id'])->on('subjects');
            $table->foreign(['school_id', 'class_id'])->references(['school_id', 'id'])->on('classes');
            $table->foreign(['school_id', 'uploaded_by'])->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enotes');
    }
};
