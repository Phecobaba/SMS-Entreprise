<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('head_title', 100);
            $table->foreignId('head_user_id')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'head_user_id']);
            $table->foreign(['school_id', 'head_user_id'], 'section_head_user_fk')->references(['school_id', 'id'])->on('users');
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'name']);
        });

        Schema::table('academic_levels', function (Blueprint $table): void {
            // Nullable only so existing schools can map their own levels without a hardcoded migration guess.
            $table->foreignId('section_id')->nullable()->after('school_id');
            $table->index(['school_id', 'section_id']);
            $table->foreign(['school_id', 'section_id'], 'academic_level_section_fk')->references(['school_id', 'id'])->on('sections');
        });

        Schema::table('classes', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('academic_level_id');
            $table->index(['school_id', 'department_id']);
            $table->foreign(['school_id', 'department_id'], 'class_department_fk')->references(['school_id', 'id'])->on('departments');
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreignId('applied_level_id')->nullable()->after('user_id');
            $table->index(['school_id', 'applied_level_id']);
            $table->foreign(['school_id', 'applied_level_id'], 'candidate_applied_level_fk')->references(['school_id', 'id'])->on('academic_levels');
        });

        Schema::table('term_default_comments', function (Blueprint $table): void {
            $table->dropUnique('term_default_comment_unique');
            $table->foreignId('section_id')->nullable()->after('term_id');
            $table->unique(['school_id', 'term_id', 'section_id', 'comment_type'], 'term_section_default_comment_unique');
            $table->foreign(['school_id', 'section_id'], 'term_default_section_fk')->references(['school_id', 'id'])->on('sections');
        });

        Schema::table('result_card_comment_audit', function (Blueprint $table): void {
            $table->foreignId('section_id')->nullable()->after('term_id');
            $table->index(['school_id', 'section_id', 'created_at']);
            $table->foreign(['school_id', 'section_id'], 'card_comment_section_fk')->references(['school_id', 'id'])->on('sections');
        });

        $permissionId = DB::table('permissions')->where('name', 'view_all_sections')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'view_all_sections',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach (DB::table('roles')->where('name', 'School Admin')->get(['id', 'school_id']) as $role) {
            DB::table('role_permissions')->updateOrInsert([
                'school_id' => $role->school_id,
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'view_all_sections')->value('id');
        if ($permissionId) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('result_card_comment_audit', function (Blueprint $table): void {
            $table->dropForeign('card_comment_section_fk');
            $table->dropIndex(['school_id', 'section_id', 'created_at']);
            $table->dropColumn('section_id');
        });
        Schema::table('term_default_comments', function (Blueprint $table): void {
            $table->dropForeign('term_default_section_fk');
            $table->dropUnique('term_section_default_comment_unique');
            $table->dropColumn('section_id');
            $table->unique(['school_id', 'term_id', 'comment_type'], 'term_default_comment_unique');
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropForeign('candidate_applied_level_fk');
            $table->dropIndex(['school_id', 'applied_level_id']);
            $table->dropColumn('applied_level_id');
        });
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropForeign('class_department_fk');
            $table->dropIndex(['school_id', 'department_id']);
            $table->dropColumn('department_id');
        });
        Schema::table('academic_levels', function (Blueprint $table): void {
            $table->dropForeign('academic_level_section_fk');
            $table->dropIndex(['school_id', 'section_id']);
            $table->dropColumn('section_id');
        });
        Schema::dropIfExists('departments');
        Schema::dropIfExists('sections');
    }
};
