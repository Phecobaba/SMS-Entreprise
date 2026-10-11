<?php

namespace App\Modules\CBT\Console;

use App\Modules\CBT\Models\Exam;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class SyncScheduledEntranceOutcomes extends Command
{
    protected $signature = 'oakfield:sync-entrance-cbt-outcomes';

    protected $description = 'Apply released entrance CBT outcomes to Admissions';

    public function handle(TenantContext $context, ExamWorkflow $workflow): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            foreach (Exam::whereNotNull('pass_mark')->where('status', 'Published')->cursor() as $exam) {
                $workflow->syncEntranceOutcomes($exam);
            }
        } finally {
            $context->clear();
        }

        return self::SUCCESS;
    }
}
