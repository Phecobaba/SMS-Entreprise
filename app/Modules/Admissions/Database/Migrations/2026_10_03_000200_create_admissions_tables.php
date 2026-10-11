<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_arms', fn (Blueprint $table) => $table->unique(['school_id', 'id']));

        Schema::create('candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('phone', 40);
            $table->string('status', 40);
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'user_id']);
            $table->foreign(['school_id', 'user_id'])->references(['school_id', 'id'])->on('users')->cascadeOnDelete();
        });

        Schema::create('candidate_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('candidate_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_override')->default(false);
            $table->timestamp('changed_at');
            $table->index(['school_id', 'candidate_id', 'changed_at']);
            $table->foreign(['school_id', 'candidate_id'])->references(['school_id', 'id'])->on('candidates')->cascadeOnDelete();
            $table->foreign(['school_id', 'changed_by'])->references(['school_id', 'id'])->on('users');
        });

        Schema::create('admission_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('label');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'label']);
        });

        Schema::create('candidate_checklist', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('candidate_id');
            $table->foreignId('checklist_item_id');
            $table->boolean('is_complete')->default(false);
            $table->foreignId('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'candidate_id', 'checklist_item_id'], 'candidate_checklist_unique');
            $table->index(['school_id', 'checklist_item_id']);
            $table->foreign(['school_id', 'candidate_id'])->references(['school_id', 'id'])->on('candidates')->cascadeOnDelete();
            $table->foreign(['school_id', 'checklist_item_id'])->references(['school_id', 'id'])->on('admission_checklist_items')->cascadeOnDelete();
            $table->foreign(['school_id', 'completed_by'])->references(['school_id', 'id'])->on('users');
        });

        Schema::create('admission_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('candidate_id');
            $table->foreignId('offered_by');
            $table->date('offer_date');
            $table->date('response_deadline');
            $table->string('status', 20)->default('Pending');
            $table->boolean('deposit_required')->default(false);
            $table->boolean('deposit_paid')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'candidate_id']);
            $table->foreign(['school_id', 'candidate_id'])->references(['school_id', 'id'])->on('candidates')->cascadeOnDelete();
            $table->foreign(['school_id', 'offered_by'])->references(['school_id', 'id'])->on('users');
        });

        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->foreignId('class_arm_id');
            $table->foreignId('candidate_id')->nullable();
            $table->date('admission_date');
            $table->timestamps();
            $table->unique(['school_id', 'user_id']);
            $table->unique(['school_id', 'candidate_id']);
            $table->index(['school_id', 'class_arm_id']);
            $table->foreign(['school_id', 'user_id'])->references(['school_id', 'id'])->on('users');
            $table->foreign(['school_id', 'class_arm_id'])->references(['school_id', 'id'])->on('class_arms');
            $table->foreign(['school_id', 'candidate_id'])->references(['school_id', 'id'])->on('candidates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('admission_offers');
        Schema::dropIfExists('candidate_checklist');
        Schema::dropIfExists('admission_checklist_items');
        Schema::dropIfExists('candidate_status_history');
        Schema::dropIfExists('candidates');
        Schema::table('class_arms', fn (Blueprint $table) => $table->dropUnique(['school_id', 'id']));
    }
};
