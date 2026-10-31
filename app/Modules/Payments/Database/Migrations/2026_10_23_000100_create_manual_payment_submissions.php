<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('invoice_id');
            $table->string('bank', 120);
            $table->string('teller_number', 120);
            $table->string('active_teller_key', 120)->nullable();
            $table->date('paid_on');
            $table->unsignedBigInteger('amount_kobo');
            $table->string('photo_path')->nullable();
            $table->foreignId('recorded_by');
            $table->string('status', 24)->default('Awaiting approval');
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'active_teller_key'], 'manual_payment_active_teller_unique');
            $table->index(['school_id', 'status', 'created_at'], 'manual_payment_queue_idx');
            $table->index(['school_id', 'invoice_id', 'status'], 'manual_payment_invoice_status_idx');
            $table->foreign(['school_id', 'invoice_id'], 'manual_payment_invoice_fk')
                ->references(['school_id', 'id'])->on('invoices');
            $table->foreign(['school_id', 'recorded_by'], 'manual_payment_recorder_fk')
                ->references(['school_id', 'id'])->on('users');
            $table->foreign(['school_id', 'decided_by'], 'manual_payment_decider_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        Schema::table('school_settings', function (Blueprint $table): void {
            $table->boolean('one_step_payment_approval')->default(false);
        });

        $permissionId = DB::table('permissions')->where('name', 'approve_payments')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'approve_payments', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (DB::table('roles')->where('name', 'School Admin')->get(['id', 'school_id']) as $role) {
            DB::table('role_permissions')->insertOrIgnore([
                'school_id' => $role->school_id,
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->dropColumn('one_step_payment_approval');
        });
        Schema::dropIfExists('manual_payment_submissions');
    }
};
