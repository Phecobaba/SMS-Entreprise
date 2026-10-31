<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('result_templates')->whereNull('section_id')->count();
        if ($missing > 0) {
            throw new RuntimeException("Cannot require Result-template Sections: {$missing} template(s) remain unassigned.");
        }

        Schema::table('result_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('result_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });
    }
};
