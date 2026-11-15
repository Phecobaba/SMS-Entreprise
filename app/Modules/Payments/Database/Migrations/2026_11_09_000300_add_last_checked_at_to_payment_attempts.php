<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->timestamp('last_checked_at')->nullable()->after('status');
            $table->index(['school_id', 'status', 'created_at'], 'payment_attempt_recheck_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex('payment_attempt_recheck_idx');
            $table->dropColumn('last_checked_at');
        });
    }
};
