<?php

use App\Modules\Academics\Services\LearnerTerminology;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->string('learner_term', 12)->default('Student')->after('head_title');
        });

        DB::table('sections')->orderBy('id')->select(['id', 'name'])->eachById(
            fn ($section) => DB::table('sections')->where('id', $section->id)->update([
                'learner_term' => LearnerTerminology::defaultForExistingSectionName($section->name),
            ])
        );
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn('learner_term');
        });
    }
};
