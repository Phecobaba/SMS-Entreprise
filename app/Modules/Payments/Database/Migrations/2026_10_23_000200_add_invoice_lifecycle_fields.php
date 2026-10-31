<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('parent_invoice_id')->nullable()->after('id');
            $table->text('notes')->nullable()->after('creation_source');
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->index(['school_id', 'parent_invoice_id', 'status'], 'invoice_parent_status_idx');
            $table->foreign(['school_id', 'parent_invoice_id'], 'invoice_parent_fk')
                ->references(['school_id', 'id'])->on('invoices');
            $table->foreign(['school_id', 'voided_by'], 'invoice_voider_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->string('line_type', 20)->default('charge')->after('description');
            $table->text('adjustment_reason')->nullable()->after('is_required');
            $table->foreignId('created_by')->nullable()->after('adjustment_reason');
            $table->foreign(['school_id', 'created_by'], 'invoice_line_creator_fk')
                ->references(['school_id', 'id'])->on('users');
        });
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->bigInteger('amount_kobo')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropForeign('invoice_line_creator_fk');
            $table->dropColumn(['line_type', 'adjustment_reason', 'created_by']);
        });
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_kobo')->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('invoice_parent_fk');
            $table->dropForeign('invoice_voider_fk');
            $table->dropIndex('invoice_parent_status_idx');
            $table->dropColumn(['parent_invoice_id', 'notes', 'voided_at', 'voided_by', 'void_reason']);
        });
    }
};
