<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registration_number_history', function (Blueprint $table): void {
            $table->unsignedSmallInteger('issued_year')->nullable()->after('sequence_value');
            $table->string('section_code', 6)->nullable()->after('issued_year');
            $table->string('level_code', 6)->nullable()->after('section_code');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('registration_number_snapshot', 64)->nullable()->after('student_id');
        });

        DB::table('student_registration_number_history')->orderBy('id')->each(function ($history): void {
            $student = DB::table('students')->where('id', $history->student_id)->first();
            $level = $student ? DB::table('class_arms')
                ->join('classes', 'classes.id', '=', 'class_arms.class_id')
                ->join('academic_levels', 'academic_levels.id', '=', 'classes.academic_level_id')
                ->leftJoin('sections', 'sections.id', '=', 'academic_levels.section_id')
                ->where('class_arms.id', $student->class_arm_id)
                ->select('academic_levels.registration_code as level_code', 'sections.registration_code as section_code')
                ->first() : null;

            DB::table('student_registration_number_history')->where('id', $history->id)->update([
                'issued_year' => (int) substr((string) $history->changed_at, 0, 4),
                'section_code' => $level?->section_code,
                'level_code' => $level?->level_code,
            ]);
        });

        DB::table('invoices')->whereNotNull('student_id')->orderBy('id')->each(function ($invoice): void {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'registration_number_snapshot' => DB::table('students')->where('id', $invoice->student_id)->value('registration_number'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('registration_number_snapshot'));
        Schema::table('student_registration_number_history', fn (Blueprint $table) => $table->dropColumn([
            'issued_year', 'section_code', 'level_code',
        ]));
    }
};
