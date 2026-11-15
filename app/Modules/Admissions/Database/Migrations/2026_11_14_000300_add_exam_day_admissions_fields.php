<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_levels', function (Blueprint $table): void {
            $table->boolean('assessed_in_person')->default(false);
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreignId('application_id')->nullable();
            $table->unique(['school_id', 'application_id']);
            $table->foreign(['school_id', 'application_id'], 'candidate_application_fk')
                ->references(['school_id', 'id'])->on('applications');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->string('assessment_outcome', 30)->nullable();
            $table->text('assessment_note')->nullable();
            $table->foreignId('assessment_recorded_by')->nullable();
            $table->timestamp('assessment_recorded_at')->nullable();
            $table->foreign(['school_id', 'assessment_recorded_by'], 'application_assessment_actor_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        Schema::table('school_settings', function (Blueprint $table): void {
            $table->string('whatsapp_number', 40)->nullable();
            $table->string('opening_hours', 200)->nullable();
            $table->string('admissions_key_dates', 300)->nullable();
            $table->string('admissions_reopen_text', 300)->nullable();
            $table->string('privacy_contact_email')->nullable();
            $table->date('privacy_last_updated_at')->nullable();
            $table->string('non_admitted_retention_text', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'whatsapp_number', 'opening_hours', 'admissions_key_dates', 'admissions_reopen_text',
                'privacy_contact_email', 'privacy_last_updated_at', 'non_admitted_retention_text',
            ]);
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign('application_assessment_actor_fk');
            $table->dropColumn(['assessment_outcome', 'assessment_note', 'assessment_recorded_by', 'assessment_recorded_at']);
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropForeign('candidate_application_fk');
            $table->dropUnique(['school_id', 'application_id']);
            $table->dropColumn('application_id');
        });
        Schema::table('academic_levels', fn (Blueprint $table) => $table->dropColumn('assessed_in_person'));
    }
};
