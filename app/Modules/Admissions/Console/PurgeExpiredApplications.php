<?php

namespace App\Modules\Admissions\Console;

use App\Modules\Admissions\Models\Application;
use App\Modules\Core\Models\School;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeExpiredApplications extends Command
{
    protected $signature = 'oakfield:purge-expired-applications';

    protected $description = 'Delete expired unpaid public applications without unresolved payment activity';

    public function handle(TenantContext $context): int
    {
        $deleted = 0;
        $kept = 0;
        foreach (School::pluck('id') as $schoolId) {
            $context->set((int) $schoolId);
            try {
                Application::where('status', '!=', 'Registered')->where('expires_at', '<=', now())->chunkById(100, function ($rows) use (&$deleted, &$kept): void {
                    foreach ($rows as $application) {
                        $unresolved = $application->attempts()->where('status', 'Pending')->exists();
                        $recent = $application->attempts()->where('last_activity_at', '>=', now()->subHours(48))->exists();
                        if ($unresolved || $recent) {
                            $kept++;

                            continue;
                        }
                        $application->update(['status' => 'Expired', 'active_username_key' => null]);
                        $application->delete();
                        $deleted++;
                    }
                });
            } finally {
                $context->clear();
            }
        }
        Log::info('Expired applications purge finished.', compact('deleted', 'kept'));
        $this->info("Deleted {$deleted}; kept {$kept} with payment activity.");

        return self::SUCCESS;
    }
}
