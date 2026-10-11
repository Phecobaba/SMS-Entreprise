<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->string('role_title');
            $table->date('employment_date');
            $table->string('contract_type', 50);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'user_id']);
            $table->index(['school_id', 'status']);
            $table->foreign(['school_id', 'user_id'], 'staff_user_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('staff_salary_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('staff_id');
            $table->unsignedBigInteger('amount_kobo');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'staff_id', 'effective_from'], 'salary_staff_date_idx');
            $table->foreign(['school_id', 'staff_id'], 'salary_staff_fk')->references(['school_id', 'id'])->on('staff');
        });

        Schema::create('staff_disciplinary_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('staff_id');
            $table->string('type', 20);
            $table->date('date');
            $table->text('description');
            $table->foreignId('issued_by');
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'staff_id', 'date'], 'discipline_staff_date_idx');
            $table->foreign(['school_id', 'staff_id'], 'discipline_staff_fk')->references(['school_id', 'id'])->on('staff');
            $table->foreign(['school_id', 'issued_by'], 'discipline_issuer_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('staff_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('staff_id');
            $table->string('document_type', 100);
            $table->string('file_path');
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->index(['school_id', 'staff_id']);
            $table->foreign(['school_id', 'staff_id'], 'staff_doc_staff_fk')->references(['school_id', 'id'])->on('staff');
        });

        Schema::create('staff_exit_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('staff_id');
            $table->string('exit_type', 20);
            $table->date('exit_date');
            $table->unsignedSmallInteger('notice_period')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'staff_id']);
            $table->foreign(['school_id', 'staff_id'], 'staff_exit_staff_fk')->references(['school_id', 'id'])->on('staff');
        });

        Schema::create('staff_sensitive_view_audit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('staff_id');
            $table->foreignId('viewed_by');
            $table->string('data_type', 20);
            $table->timestamp('viewed_at');
            $table->index(['school_id', 'staff_id', 'data_type']);
            $table->foreign(['school_id', 'staff_id'], 'staff_audit_staff_fk')->references(['school_id', 'id'])->on('staff');
            $table->foreign(['school_id', 'viewed_by'], 'staff_audit_viewer_fk')->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sensitive_view_audit');
        Schema::dropIfExists('staff_exit_records');
        Schema::dropIfExists('staff_documents');
        Schema::dropIfExists('staff_disciplinary_log');
        Schema::dropIfExists('staff_salary_history');
        Schema::dropIfExists('staff');
    }
};
