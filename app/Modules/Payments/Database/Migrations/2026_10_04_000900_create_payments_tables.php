<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable();
            $table->foreignId('candidate_id')->nullable();
            $table->foreignId('term_id')->nullable();
            $table->string('purpose', 30);
            $table->unsignedBigInteger('amount_kobo');
            $table->date('due_date');
            $table->string('status', 20)->default('Unpaid');
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'student_id', 'term_id', 'status'], 'invoice_student_term_status_idx');
            $table->index(['school_id', 'candidate_id', 'status'], 'invoice_candidate_status_idx');
            $table->foreign(['school_id', 'student_id'], 'invoice_student_fk')->references(['school_id', 'id'])->on('students');
            $table->foreign(['school_id', 'candidate_id'], 'invoice_candidate_fk')->references(['school_id', 'id'])->on('candidates');
            $table->foreign(['school_id', 'term_id'], 'invoice_term_fk')->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'created_by'], 'invoice_creator_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('invoice_id');
            $table->string('reference', 80)->unique();
            $table->unsignedBigInteger('amount_kobo');
            $table->foreignId('initiated_by');
            $table->string('authorization_url', 2048)->nullable();
            $table->string('status', 20)->default('Pending');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'invoice_id', 'status']);
            $table->foreign(['school_id', 'invoice_id'], 'payment_attempt_invoice_fk')->references(['school_id', 'id'])->on('invoices');
            $table->foreign(['school_id', 'initiated_by'], 'payment_attempt_initiator_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('invoice_id');
            $table->unsignedBigInteger('amount_kobo');
            $table->string('method', 20);
            $table->string('gateway_reference', 80)->nullable();
            $table->foreignId('confirmed_by')->nullable();
            $table->timestamp('paid_at');
            $table->string('receipt_number', 60)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'invoice_id']);
            $table->unique(['school_id', 'gateway_reference']);
            $table->unique(['school_id', 'receipt_number']);
            $table->foreign(['school_id', 'invoice_id'], 'payment_invoice_fk')->references(['school_id', 'id'])->on('invoices');
            $table->foreign(['school_id', 'confirmed_by'], 'payment_confirmer_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->date('date');
            $table->string('category', 100);
            $table->unsignedBigInteger('amount_kobo');
            $table->string('vendor');
            $table->string('receipt_file_path')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->foreignId('created_by');
            $table->timestamps();
            $table->index(['school_id', 'date']);
            $table->foreign(['school_id', 'approved_by'], 'expense_approver_fk')->references(['school_id', 'id'])->on('users');
            $table->foreign(['school_id', 'created_by'], 'expense_creator_fk')->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('invoices');
    }
};
