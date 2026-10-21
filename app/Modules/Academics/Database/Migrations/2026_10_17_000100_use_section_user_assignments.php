<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('section_id');
            $table->foreignId('user_id');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'section_id', 'user_id']);
            $table->foreign(['school_id', 'section_id'], 'section_user_section_fk')
                ->references(['school_id', 'id'])->on('sections')->cascadeOnDelete();
            $table->foreign(['school_id', 'user_id'], 'section_user_user_fk')
                ->references(['school_id', 'id'])->on('users')->cascadeOnDelete();
        });

        foreach (DB::table('sections')->whereNotNull('head_user_id')->get(['id', 'school_id', 'head_user_id']) as $section) {
            DB::table('section_user')->insert([
                'school_id' => $section->school_id,
                'section_id' => $section->id,
                'user_id' => $section->head_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('sections', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['school_id', 'head_user_id']);
            } else {
                $table->dropForeign('section_head_user_fk');
            }
            $table->dropIndex(['school_id', 'head_user_id']);
            $table->dropColumn('head_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->foreignId('head_user_id')->nullable();
            $table->index(['school_id', 'head_user_id']);
            $table->foreign(['school_id', 'head_user_id'], 'section_head_user_fk')
                ->references(['school_id', 'id'])->on('users');
        });

        foreach (DB::table('section_user')->orderBy('id')->get()->groupBy('section_id') as $sectionId => $rows) {
            DB::table('sections')->where('id', $sectionId)->update(['head_user_id' => $rows->first()->user_id]);
        }

        Schema::dropIfExists('section_user');
    }
};
