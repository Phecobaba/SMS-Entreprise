<?php

namespace App\Modules\Payments\Console;

use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Admissions\Services\ApplicationWorkflow;
use App\Modules\Core\Models\School;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Services\PaymentLedger;
use App\Modules\Payments\Services\PaystackCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecheckPendingPaystackPayments extends Command
{
    protected $signature = 'oakfield:recheck-pending-paystack';

    protected $description = 'Recheck recent pending Paystack attempts in small tenant-safe batches';

    public function handle(TenantContext $context, PaymentLedger $ledger, ApplicationWorkflow $applications): int
    {
        $counts = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'confirming' => 0, 'errors' => 0];

        foreach (School::query()->pluck('id') as $schoolId) {
            if ($counts['checked'] >= 50) {
                break;
            }
            $context->set((int) $schoolId);
            try {
                if (! app(PaystackCredentials::class)->isConfigured()) {
                    continue;
                }
                $attempts = PaymentAttempt::query()->where('status', 'Pending')
                    ->where('created_at', '<=', now()->subMinutes(2))
                    ->where('created_at', '>=', now()->subHours(48))
                    ->oldest('id')->limit(50 - $counts['checked'])->get();
                foreach ($attempts as $attempt) {
                    $counts['checked']++;
                    try {
                        $result = $ledger->verifyPaystackResult($attempt->reference)['status'];
                        $key = match ($result) {
                            PaymentLedger::PAYSTACK_PAID => 'paid',
                            PaymentLedger::PAYSTACK_FAILED => 'failed',
                            default => 'confirming',
                        };
                        $counts[$key]++;
                    } catch (Throwable) {
                        $counts['errors']++;
                    }
                }
                if ($counts['checked'] < 50) {
                    $applicationAttempts = ApplicationPaymentAttempt::where('status', 'Pending')
                        ->where('created_at', '<=', now()->subMinutes(2))->where('created_at', '>=', now()->subDays(7))
                        ->oldest('id')->limit(50 - $counts['checked'])->get();
                    foreach ($applicationAttempts as $attempt) {
                        $counts['checked']++;
                        try {
                            $result = $applications->verifyReference($attempt->reference);
                            $key = match ($result) {
                                ApplicationWorkflow::REGISTERED => 'paid',
                                ApplicationWorkflow::FAILED => 'failed',
                                default => 'confirming',
                            };
                            $counts[$key]++;
                        } catch (Throwable) {
                            $counts['errors']++;
                        }
                    }
                }
            } finally {
                $context->clear();
            }
        }

        Log::info('Scheduled Paystack recheck finished.', $counts);
        $this->info(vsprintf('Checked %d; paid %d; failed %d; still confirming %d; errors %d.', array_values($counts)));

        return self::SUCCESS;
    }
}
