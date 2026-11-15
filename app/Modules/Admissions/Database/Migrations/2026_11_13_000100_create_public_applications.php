<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->boolean('admissions_open')->default(true);
            $table->boolean('application_fee_enabled')->default(false);
            $table->unsignedBigInteger('application_fee_kobo')->default(0);
            $table->text('admissions_privacy_text')->nullable();
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('public_reference', 40)->unique();
            $table->string('child_first_name', 100);
            $table->string('child_surname', 100);
            $table->string('child_other_names', 150)->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 20);
            $table->foreignId('applied_level_id');
            $table->string('previous_school')->nullable();
            $table->string('guardian_name');
            $table->string('guardian_relationship', 60);
            $table->string('guardian_phone', 40);
            $table->string('guardian_email');
            $table->text('guardian_address');
            $table->string('username', 30);
            $table->string('active_username_key', 30)->nullable();
            $table->string('status', 30)->default('Awaiting payment');
            $table->unsignedBigInteger('fee_amount_kobo')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('access_token_hash', 64)->unique();
            $table->timestamp('consented_at');
            $table->string('consent_version', 40);
            $table->timestamp('expires_at');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedBigInteger('confirmed_attempt_id')->nullable();
            $table->timestamp('submission_email_sent_at')->nullable();
            $table->timestamp('confirmation_email_sent_at')->nullable();
            $table->timestamp('mail_failed_at')->nullable();
            $table->boolean('needs_attention')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'active_username_key']);
            $table->index(['school_id', 'status', 'created_at']);
            $table->index(['school_id', 'expires_at']);
            $table->foreign(['school_id', 'applied_level_id'], 'application_level_fk')->references(['school_id', 'id'])->on('academic_levels');
        });

        Schema::create('application_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('application_id');
            $table->unsignedBigInteger('active_application_id')->nullable();
            $table->string('reference', 80)->unique();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->string('authorization_url', 2048)->nullable();
            $table->string('status', 20)->default('Pending');
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('late_success')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'active_application_id']);
            $table->index(['school_id', 'status', 'created_at'], 'application_attempt_recheck_idx');
            $table->foreign(['school_id', 'application_id'], 'application_attempt_application_fk')->references(['school_id', 'id'])->on('applications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_payment_attempts');
        Schema::dropIfExists('applications');
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->dropColumn(['admissions_open', 'application_fee_enabled', 'application_fee_kobo', 'admissions_privacy_text']);
        });
    }
};
