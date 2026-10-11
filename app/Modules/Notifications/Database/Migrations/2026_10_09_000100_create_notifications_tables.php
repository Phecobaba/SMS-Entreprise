<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->string('channel', 20)->default('In App');
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'user_id', 'read_at', 'created_at'], 'notification_inbox_idx');
            $table->foreign(['school_id', 'user_id'], 'notification_user_fk')->references(['school_id', 'id'])->on('users')->cascadeOnDelete();
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('notification_id');
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_id', 'notification_id']);
            $table->foreign(['school_id', 'notification_id'], 'notification_log_notification_fk')->references(['school_id', 'id'])->on('notifications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
    }
};
