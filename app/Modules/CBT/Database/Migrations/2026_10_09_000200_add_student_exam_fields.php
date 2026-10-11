<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->timestamp('closes_at')->nullable()->after('scheduled_at');
        });

        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->foreignId('selected_option_id')->nullable()->change();
            $table->boolean('flagged')->default(false)->after('selected_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->dropColumn('flagged');
            $table->foreignId('selected_option_id')->nullable(false)->change();
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->dropColumn('closes_at');
        });
    }
};
