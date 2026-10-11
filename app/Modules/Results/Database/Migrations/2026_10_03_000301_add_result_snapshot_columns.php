<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table): void {
            $table->foreignId('class_arm_id');
            $table->foreignId('result_template_id');
            $table->index(['school_id', 'class_arm_id', 'term_id'], 'result_arm_term_index');
            $table->foreign(['school_id', 'class_arm_id'], 'result_arm_fk')->references(['school_id', 'id'])->on('class_arms');
            $table->foreign(['school_id', 'result_template_id'], 'result_template_fk')->references(['school_id', 'id'])->on('result_templates');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table): void {
            $table->dropForeign('result_arm_fk');
            $table->dropForeign('result_template_fk');
            $table->dropIndex('result_arm_term_index');
            $table->dropColumn(['class_arm_id', 'result_template_id']);
        });
    }
};
