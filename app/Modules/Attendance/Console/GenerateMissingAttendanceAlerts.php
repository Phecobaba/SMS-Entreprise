<?php

namespace App\Modules\Attendance\Console;

use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Core\Models\School;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class GenerateMissingAttendanceAlerts extends Command
{
    protected $signature = 'oakfield:attendance-alerts {--date= : School date (YYYY-MM-DD); defaults to today in WAT} {--session= : Morning or Afternoon; defaults to both}';

    protected $description = 'Flag enrolled class arms whose due Morning or Afternoon roll is incomplete';

    public function handle(TenantContext $context, AttendanceService $attendance): int
    {
        $date = $this->option('date') ?: now('Africa/Lagos')->toDateString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Use YYYY-MM-DD for --date.');

            return self::FAILURE;
        }
        $sessions = $this->option('session') ? [(string) $this->option('session')] : AttendanceService::SESSIONS;
        if (array_diff($sessions, AttendanceService::SESSIONS)) {
            $this->error('Use Morning or Afternoon for --session.');

            return self::FAILURE;
        }
        $count = 0;
        foreach (School::query()->pluck('id') as $schoolId) {
            $context->set((int) $schoolId);
            try {
                foreach ($sessions as $session) {
                    $count += $attendance->generateMissingAlerts($date, $session);
                }
            } finally {
                $context->clear();
            }
        }
        $this->info("Generated {$count} new attendance alert(s) for {$date}.");

        return self::SUCCESS;
    }
}
