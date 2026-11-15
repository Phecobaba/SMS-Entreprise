<?php

namespace App\Modules\CBT\Console;

use App\Modules\CBT\Services\CbtQuestionCsv;
use Illuminate\Console\Command;

class CleanupQuestionUploads extends Command
{
    protected $signature = 'oakfield:cleanup-cbt-question-uploads';

    protected $description = 'Delete expired private CBT question CSV uploads';

    public function handle(CbtQuestionCsv $uploads): int
    {
        $this->info('Expired CBT question uploads deleted: '.$uploads->cleanupExpiredFiles().'.');

        return self::SUCCESS;
    }
}
