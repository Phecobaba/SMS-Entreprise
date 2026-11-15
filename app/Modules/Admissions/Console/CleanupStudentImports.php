<?php

namespace App\Modules\Admissions\Console;

use App\Modules\Admissions\Services\StudentImportService;
use App\Modules\Core\Models\School;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;

class CleanupStudentImports extends Command
{
    protected $signature = 'oakfield:cleanup-student-imports';

    protected $description = 'Delete expired private Student import uploads while retaining their reports';

    public function handle(StudentImportService $imports, TenantContext $tenant): int
    {
        $cleaned = 0;
        foreach (School::query()->pluck('id') as $schoolId) {
            $tenant->set((int) $schoolId);
            $cleaned += $imports->cleanupExpired();
        }

        $this->info("Expired Student import uploads deleted: {$cleaned}.");

        return self::SUCCESS;
    }
}
