<?php

namespace App\Modules\Admissions\Console;

use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class LapseExpiredOffers extends Command
{
    protected $signature = 'oakfield:lapse-expired-offers';

    protected $description = 'Lapse unanswered admission offers past their response deadline';

    public function handle(TenantContext $context, AdmissionPipeline $pipeline): int
    {
        $context->set((int) config('tenancy.default_school_id'));
        $count = 0;

        try {
            AdmissionOffer::where('status', 'Pending')->whereDate('response_deadline', '<', today())
                ->chunkById(100, function ($offers) use ($pipeline, &$count): void {
                    foreach ($offers as $offer) {
                        $candidate = Candidate::find($offer->candidate_id);
                        if (! $candidate) {
                            continue;
                        }
                        try {
                            $pipeline->lapse($candidate);
                            $count++;
                        } catch (ValidationException) {
                            // The candidate responded while the scheduled scan was running.
                        }
                    }
                });
        } finally {
            $context->clear();
        }

        $this->info("Lapsed {$count} expired admission offer(s).");

        return self::SUCCESS;
    }
}
