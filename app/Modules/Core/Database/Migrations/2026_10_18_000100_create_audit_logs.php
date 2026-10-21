<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->string('action', 100);
            $table->string('subject_type', 150)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['school_id', 'created_at']);
            $table->index(['school_id', 'subject_type', 'subject_id']);
            $table->foreign(['school_id', 'user_id'], 'audit_log_user_fk')
                ->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
