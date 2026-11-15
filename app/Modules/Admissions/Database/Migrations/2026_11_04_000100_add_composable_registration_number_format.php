<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->boolean('registration_include_school')->default(true);
            $table->boolean('registration_include_section')->default(false);
            $table->boolean('registration_include_level')->default(false);
            $table->string('registration_separator', 1)->default('-');
            $table->unsignedTinyInteger('registration_year_digits')->default(4);
            $table->string('registration_school_code', 6)->default('REG');
            $table->unsignedBigInteger('registration_next_number')->default(1);
        });
        Schema::table('sections', fn (Blueprint $table) => $table->string('registration_code', 6)->nullable());
        Schema::table('academic_levels', fn (Blueprint $table) => $table->string('registration_code', 6)->nullable());

        Schema::table('students', fn (Blueprint $table) => $table->string('registration_number', 64)->nullable()->change());
        Schema::table('student_registration_number_history', function (Blueprint $table): void {
            $table->string('old_number', 64)->nullable()->change();
            $table->string('new_number', 64)->change();
        });
        Schema::table('registration_number_sequences', fn (Blueprint $table) => $table->string('sequence_key', 128)->change());

        DB::table('school_settings')->orderBy('school_id')->each(function ($settings): void {
            $schoolCode = strtoupper(trim((string) ($settings->registration_prefix ?: 'REG')));
            $next = (int) (DB::table('registration_number_sequences')
                ->where('school_id', $settings->school_id)->max('next_number') ?: 1);

            DB::table('school_settings')->where('school_id', $settings->school_id)->update([
                // The old allocator emitted one effective Level/Section/school prefix.
                // Store that effective value on every Level and turn on Level only.
                'registration_include_school' => false,
                'registration_include_section' => false,
                'registration_include_level' => true,
                'registration_separator' => '-',
                'registration_year_digits' => 4,
                'registration_school_code' => $schoolCode,
                'registration_next_number' => $next,
            ]);

            $sections = DB::table('sections')->where('school_id', $settings->school_id)->get();
            foreach ($sections as $section) {
                $sectionCode = strtoupper(trim((string) ($section->registration_prefix ?: $schoolCode)));
                DB::table('sections')->where('id', $section->id)->update(['registration_code' => $sectionCode]);
                DB::table('academic_levels')->where('school_id', $settings->school_id)
                    ->where('section_id', $section->id)->orderBy('id')->each(function ($level) use ($sectionCode): void {
                        DB::table('academic_levels')->where('id', $level->id)->update([
                            'registration_code' => strtoupper(trim((string) ($level->registration_prefix ?: $sectionCode))),
                        ]);
                    });
            }

            DB::table('academic_levels')->where('school_id', $settings->school_id)
                ->whereNull('section_id')->orderBy('id')->each(function ($level) use ($schoolCode): void {
                    DB::table('academic_levels')->where('id', $level->id)->update([
                        'registration_code' => strtoupper(trim((string) ($level->registration_prefix ?: $schoolCode))),
                    ]);
                });
        });
    }

    public function down(): void
    {
        Schema::table('registration_number_sequences', fn (Blueprint $table) => $table->string('sequence_key', 32)->change());
        Schema::table('student_registration_number_history', function (Blueprint $table): void {
            $table->string('old_number', 32)->nullable()->change();
            $table->string('new_number', 32)->change();
        });
        Schema::table('students', fn (Blueprint $table) => $table->string('registration_number', 32)->nullable()->change());
        Schema::table('academic_levels', fn (Blueprint $table) => $table->dropColumn('registration_code'));
        Schema::table('sections', fn (Blueprint $table) => $table->dropColumn('registration_code'));
        Schema::table('school_settings', fn (Blueprint $table) => $table->dropColumn([
            'registration_include_school', 'registration_include_section', 'registration_include_level',
            'registration_separator', 'registration_year_digits', 'registration_school_code',
            'registration_next_number',
        ]));
    }
};
