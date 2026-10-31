<?php

use App\Modules\Core\Services\DefaultRoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table): void {
            $table->string('registration_prefix', 6)->default('REG');
            $table->unsignedTinyInteger('registration_number_length')->default(4);
            $table->boolean('registration_include_year')->default(false);
            $table->string('registration_move_policy', 40)->default('keep_digits_change_prefix');
            $table->timestamp('registration_move_policy_locked_at')->nullable();
        });
        Schema::table('sections', fn (Blueprint $table) => $table->string('registration_prefix', 6)->nullable());
        Schema::table('academic_levels', fn (Blueprint $table) => $table->string('registration_prefix', 6)->nullable());
        Schema::table('students', function (Blueprint $table): void {
            $table->string('registration_number', 32)->nullable()->after('id');
            $table->unique(['school_id', 'registration_number'], 'student_registration_unique');
        });

        Schema::create('registration_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('sequence_key', 32);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['school_id', 'sequence_key'], 'registration_sequence_unique');
        });

        Schema::create('student_registration_number_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->string('old_number', 32)->nullable();
            $table->string('new_number', 32);
            $table->unsignedBigInteger('sequence_value')->nullable();
            $table->foreignId('changed_by');
            $table->timestamp('changed_at');
            $table->unique(['school_id', 'new_number'], 'registration_history_reserved_unique');
            $table->index(['school_id', 'student_id', 'changed_at'], 'registration_history_student_idx');
            $table->foreign(['school_id', 'student_id'], 'registration_history_student_fk')
                ->references(['school_id', 'id'])->on('students')->cascadeOnDelete();
            $table->foreign(['school_id', 'changed_by'], 'registration_history_actor_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        app(DefaultRoleCatalog::class)->addMissingPermissionsToExistingRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registration_number_history');
        Schema::dropIfExists('registration_number_sequences');
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique('student_registration_unique');
            $table->dropColumn('registration_number');
        });
        Schema::table('academic_levels', fn (Blueprint $table) => $table->dropColumn('registration_prefix'));
        Schema::table('sections', fn (Blueprint $table) => $table->dropColumn('registration_prefix'));
        Schema::table('school_settings', fn (Blueprint $table) => $table->dropColumn([
            'registration_prefix', 'registration_number_length', 'registration_include_year',
            'registration_move_policy', 'registration_move_policy_locked_at',
        ]));
    }
};
