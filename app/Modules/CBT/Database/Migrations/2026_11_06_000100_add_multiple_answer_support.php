<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_options', function (Blueprint $table): void {
            $table->unsignedSmallInteger('marks')->default(0)->after('is_correct');
        });
        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->unique(['school_id', 'id'], 'exam_answers_school_id_id_unique');
        });

        Schema::create('exam_answer_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('exam_answer_id');
            $table->foreignId('question_option_id');
            $table->timestamps();
            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'exam_answer_id', 'question_option_id'], 'answer_selection_unique');
            $table->foreign(['school_id', 'exam_answer_id'], 'selection_answer_fk')
                ->references(['school_id', 'id'])->on('exam_answers')->cascadeOnDelete();
            $table->foreign(['school_id', 'question_option_id'], 'selection_option_fk')
                ->references(['school_id', 'id'])->on('question_options');
        });

        DB::table('question_options')
            ->join('questions', function ($join): void {
                $join->on('questions.id', '=', 'question_options.question_id')
                    ->on('questions.school_id', '=', 'question_options.school_id');
            })
            ->where('question_options.is_correct', true)
            ->select('question_options.id', 'question_options.school_id', 'questions.marks')
            ->orderBy('question_options.id')
            ->each(function ($option): void {
                // Legacy questions already carried their whole-question weight. Preserve it.
                $marks = max(1, (int) round((float) $option->marks));
                DB::table('question_options')->where('id', $option->id)
                    ->where('school_id', $option->school_id)->update(['marks' => $marks]);
            });

        DB::table('exam_answers')->whereNotNull('selected_option_id')->orderBy('id')
            ->each(function ($answer): void {
                DB::table('exam_answer_selections')->insertOrIgnore([
                    'school_id' => $answer->school_id,
                    'exam_answer_id' => $answer->id,
                    'question_option_id' => $answer->selected_option_id,
                    'created_at' => $answer->created_at,
                    'updated_at' => $answer->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answer_selections');
        Schema::table('exam_answers', function (Blueprint $table): void {
            $table->dropUnique('exam_answers_school_id_id_unique');
        });
        Schema::table('question_options', function (Blueprint $table): void {
            $table->dropColumn('marks');
        });
    }
};
