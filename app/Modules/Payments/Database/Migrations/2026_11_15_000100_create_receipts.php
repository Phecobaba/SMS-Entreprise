<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->unsignedSmallInteger('issue_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['school_id', 'issue_year']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable();
            $table->foreignId('application_id')->nullable();
            $table->foreignId('application_payment_attempt_id')->nullable();
            $table->string('number', 30);
            $table->string('school_name');
            $table->string('school_address')->nullable();
            $table->string('school_phone', 40)->nullable();
            $table->mediumText('school_logo_data_uri')->nullable();
            $table->string('payer_name');
            $table->string('child_name')->nullable();
            $table->string('class_or_level')->nullable();
            $table->json('items');
            $table->unsignedBigInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->string('method', 40);
            $table->string('gateway_reference', 100)->nullable();
            $table->unsignedBigInteger('balance_after_kobo')->nullable();
            $table->string('received_by_name')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('mail_failed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'number']);
            $table->unique(['school_id', 'payment_id']);
            $table->index(['school_id', 'application_id']);
            $table->unique(['school_id', 'application_payment_attempt_id'], 'receipt_school_application_attempt_unique');
            $table->index(['school_id', 'issued_at']);
            $table->foreign(['school_id', 'payment_id'], 'receipt_payment_fk')->references(['school_id', 'id'])->on('payments');
            $table->foreign(['school_id', 'application_id'], 'receipt_application_fk')->references(['school_id', 'id'])->on('applications');
            $table->foreign(['school_id', 'application_payment_attempt_id'], 'receipt_application_attempt_fk')->references(['school_id', 'id'])->on('application_payment_attempts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_counters');
    }
};
