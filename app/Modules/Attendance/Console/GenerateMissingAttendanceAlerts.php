<?php

namespace App\Modules\Attendance\Console;

use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GenerateMissingAttendanceAlerts extends Command
{
    protected $signature = 'oakfield:attendance-alerts {--date= : School date (YYYY-MM-DD); defaults to today in WAT}';

    protected $description = 'Flag enrolled class arms whose daily roster has not been fully marked';

    public function handle(TenantContext $context, AttendanceService $attendance): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        try {
            $date = $this->option('date') ?: now('Africa/Lagos')->toDateString();
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error('Use YYYY-MM-DD for --date.');

                return self::FAILURE;
            }
            $count = $attendance->generateMissingAlerts($date);
            $this->info("Generated {$count} new attendance alert(s) for {$date}.");
        } finally {
            $context->clear();
        }

        return self::SUCCESS;
    }
}
