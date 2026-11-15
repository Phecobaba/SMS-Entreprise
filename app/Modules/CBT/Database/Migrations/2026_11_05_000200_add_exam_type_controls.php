<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_types', function (Blueprint $table): void {
            $table->boolean('feeds_results')->default(false)->after('name');
            $table->boolean('active')->default(true)->after('feeds_results');
        });

        $now = now();
        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            $type = DB::table('exam_types')->where('school_id', $schoolId)->where('name', 'School exam')->first();
            if ($type) {
                DB::table('exam_types')->where('id', $type->id)->update(['feeds_results' => true, 'active' => true, 'updated_at' => $now]);
            } else {
                DB::table('exam_types')->insert([
                    'school_id' => $schoolId, 'name' => 'School exam', 'feeds_results' => true,
                    'active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('exam_types', function (Blueprint $table): void {
            $table->dropColumn(['feeds_results', 'active']);
        });
    }
};
