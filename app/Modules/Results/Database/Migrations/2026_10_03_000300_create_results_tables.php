<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', fn (Blueprint $table) => $table->unique(['school_id', 'id']));
        Schema::table('students', fn (Blueprint $table) => $table->unique(['school_id', 'id']));

        Schema::create('assessment_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('max_score', 6, 2);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('grading_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('grade_bands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grading_scheme_id');
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->string('grade', 20);
            $table->string('remark', 100)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'grading_scheme_id', 'min_score'], 'grade_band_start_unique');
            $table->index(['school_id', 'grading_scheme_id'], 'grade_band_scheme_index');
            $table->foreign(['school_id', 'grading_scheme_id'], 'grade_band_scheme_fk')->references(['school_id', 'id'])->on('grading_schemes')->cascadeOnDelete();
        });

        Schema::create('result_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_level_id');
            $table->foreignId('class_id')->nullable();
            $table->foreignId('grading_scheme_id');
            $table->string('name', 100);
            $table->string('scope_key', 40);
            $table->timestamps();
            $table->unique(['school_id', 'scope_key']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'academic_level_id']);
            $table->index(['school_id', 'class_id']);
            $table->foreign(['school_id', 'academic_level_id'], 'result_template_level_fk')->references(['school_id', 'id'])->on('academic_levels');
            $table->foreign(['school_id', 'class_id'], 'result_template_class_fk')->references(['school_id', 'id'])->on('classes');
            $table->foreign(['school_id', 'grading_scheme_id'], 'result_template_scheme_fk')->references(['school_id', 'id'])->on('grading_schemes');
        });

        Schema::create('result_template_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('result_template_id');
            $table->foreignId('assessment_component_id');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['school_id', 'result_template_id', 'assessment_component_id'], 'template_component_unique');
            $table->unique(['school_id', 'result_template_id', 'position'], 'template_position_unique');
            $table->index(['school_id', 'assessment_component_id'], 'template_component_index');
            $table->foreign(['school_id', 'result_template_id'], 'template_component_template_fk')->references(['school_id', 'id'])->on('result_templates')->cascadeOnDelete();
            $table->foreign(['school_id', 'assessment_component_id'], 'template_component_component_fk')->references(['school_id', 'id'])->on('assessment_components');
        });

        Schema::create('results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->foreignId('subject_id');
            $table->foreignId('term_id');
            $table->string('status', 20)->default('Draft');
            $table->foreignId('submitted_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('total_score', 7, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('grade', 20)->nullable();
            $table->string('remark', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'student_id', 'subject_id', 'term_id'], 'result_scope_unique');
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'subject_id', 'term_id'], 'result_subject_term_index');
            $table->foreign(['school_id', 'student_id'], 'result_student_fk')->references(['school_id', 'id'])->on('students');
            $table->foreign(['school_id', 'subject_id'], 'result_subject_fk')->references(['school_id', 'id'])->on('subjects');
            $table->foreign(['school_id', 'term_id'], 'result_term_fk')->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'submitted_by'], 'result_submitter_fk')->references(['school_id', 'id'])->on('users');
            $table->foreign(['school_id', 'approved_by'], 'result_approver_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('result_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('result_id');
            $table->foreignId('assessment_component_id');
            $table->decimal('score', 6, 2);
            $table->timestamps();
            $table->unique(['school_id', 'result_id', 'assessment_component_id'], 'result_item_unique');
            $table->index(['school_id', 'assessment_component_id'], 'result_item_component_index');
            $table->foreign(['school_id', 'result_id'], 'result_item_result_fk')->references(['school_id', 'id'])->on('results')->cascadeOnDelete();
            $table->foreign(['school_id', 'assessment_component_id'], 'result_item_component_fk')->references(['school_id', 'id'])->on('assessment_components');
        });

        Schema::create('result_audit_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('result_id');
            $table->string('action', 30);
            $table->foreignId('user_id');
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['school_id', 'result_id', 'created_at'], 'result_audit_result_index');
            $table->foreign(['school_id', 'result_id'], 'result_audit_result_fk')->references(['school_id', 'id'])->on('results')->cascadeOnDelete();
            $table->foreign(['school_id', 'user_id'], 'result_audit_user_fk')->references(['school_id', 'id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_audit_log');
        Schema::dropIfExists('result_items');
        Schema::dropIfExists('results');
        Schema::dropIfExists('result_template_components');
        Schema::dropIfExists('result_templates');
        Schema::dropIfExists('grade_bands');
        Schema::dropIfExists('grading_schemes');
        Schema::dropIfExists('assessment_components');
        Schema::table('students', fn (Blueprint $table) => $table->dropUnique(['school_id', 'id']));
        Schema::table('terms', fn (Blueprint $table) => $table->dropUnique(['school_id', 'id']));
    }
};
