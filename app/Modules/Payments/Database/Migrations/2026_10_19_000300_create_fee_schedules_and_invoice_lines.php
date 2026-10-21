<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'name']);
        });

        Schema::create('fee_schedule_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('term_id');
            $table->string('scope_type', 20);
            $table->foreignId('academic_level_id')->nullable();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('fee_item_id');
            $table->unsignedBigInteger('amount_kobo');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'term_id', 'scope_type']);
            $table->unique(['school_id', 'term_id', 'academic_level_id', 'class_id', 'department_id', 'fee_item_id'], 'fee_schedule_scope_item_unique');
            $table->foreign(['school_id', 'term_id'])->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'academic_level_id'])->references(['school_id', 'id'])->on('academic_levels');
            $table->foreign(['school_id', 'class_id'])->references(['school_id', 'id'])->on('classes');
            $table->foreign(['school_id', 'department_id'])->references(['school_id', 'id'])->on('departments');
            $table->foreign(['school_id', 'fee_item_id'])->references(['school_id', 'id'])->on('fee_items');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('kind', 30)->default('one_off')->after('purpose');
            $table->string('creation_source', 20)->default('legacy')->after('kind');
            $table->foreignId('term_fee_student_id')->nullable()->after('student_id');
            $table->unique(['school_id', 'term_id', 'term_fee_student_id'], 'invoice_one_term_fee_unique');
            $table->foreign(['school_id', 'term_fee_student_id'], 'invoice_term_fee_student_fk')->references(['school_id', 'id'])->on('students');
        });
        DB::table('invoices')->where('purpose', 'Tuition')->update([
            'kind' => 'term_fee', 'term_fee_student_id' => DB::raw('student_id'),
        ]);
        DB::table('invoices')->where('purpose', 'Registration')->update(['kind' => 'registration']);
        DB::table('invoices')->where('purpose', 'Offer Deposit')->update(['kind' => 'offer_deposit']);

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('invoice_id');
            $table->foreignId('fee_item_id')->nullable();
            $table->string('description', 120);
            $table->unsignedBigInteger('amount_kobo');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'invoice_id']);
            $table->foreign(['school_id', 'invoice_id'])->references(['school_id', 'id'])->on('invoices')->cascadeOnDelete();
            $table->foreign(['school_id', 'fee_item_id'])->references(['school_id', 'id'])->on('fee_items');
        });

        Schema::create('invoice_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('term_id');
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('due_date');
            $table->unsignedInteger('students_in_scope');
            $table->unsignedInteger('created_count');
            $table->unsignedInteger('skipped_count');
            $table->unsignedInteger('no_class_count')->default(0);
            $table->unsignedInteger('no_schedule_count')->default(0);
            $table->foreignId('created_by');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->foreign(['school_id', 'term_id'])->references(['school_id', 'id'])->on('terms');
            $table->foreign(['school_id', 'created_by'])->references(['school_id', 'id'])->on('users');
        });

        $permissionId = DB::table('permissions')->where('name', 'manage_fee_schedule')->value('id')
            ?: DB::table('permissions')->insertGetId(['name' => 'manage_fee_schedule', 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table('roles')->whereIn('name', ['School Admin', 'Bursar'])->get(['id', 'school_id']) as $role) {
            DB::table('role_permissions')->insertOrIgnore(['school_id' => $role->school_id, 'role_id' => $role->id, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_generation_runs');
        Schema::dropIfExists('invoice_lines');
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign('invoice_term_fee_student_fk');
            $table->dropUnique('invoice_one_term_fee_unique');
            $table->dropColumn(['kind', 'creation_source', 'term_fee_student_id']);
        });
        Schema::dropIfExists('fee_schedule_lines');
        Schema::dropIfExists('fee_items');
    }
};
