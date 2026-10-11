<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->string('motto')->nullable();
            $table->timestamps();
        });

        Schema::create('school_settings', function (Blueprint $table): void {
            $table->foreignId('school_id')->primary()->constrained('schools')->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->text('about_text')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('motto')->nullable();
            $table->string('admissions_form_target')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status', 20)->default('active');
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['school_id', 'email']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('role_id');
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
            $table->index(['school_id', 'role_id']);
            $table->index('permission_id');
            $table->foreign(['school_id', 'role_id'])->references(['school_id', 'id'])->on('roles')->cascadeOnDelete();
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->primary(['user_id', 'role_id']);
            $table->index(['school_id', 'user_id']);
            $table->index(['school_id', 'role_id']);
            $table->foreign(['school_id', 'user_id'])->references(['school_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['school_id', 'role_id'])->references(['school_id', 'id'])->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('schools');
    }
};
