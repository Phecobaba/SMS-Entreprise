<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->string('file_hash', 64);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('report')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_imports');
    }
};
