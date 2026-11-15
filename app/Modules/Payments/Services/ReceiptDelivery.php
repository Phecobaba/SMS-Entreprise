<?php

namespace App\Modules\Payments\Services;

use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\RecoveryMailer;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Mail\ReceiptIssuedMail;
use App\Modules\Payments\Models\Receipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ReceiptDelivery
{
    public function defer(Receipt $receipt, ?string $address): void
    {
        if (! $address) {
            return;
        }
        $receiptId = $receipt->id;
        $schoolId = $receipt->school_id;
        defer(function () use ($receiptId, $schoolId, $address): void {
            app(TenantContext::class)->set((int) $schoolId);
            $record = Receipt::find($receiptId);
            if (! $record) {
                return;
            }
            $this->send($record, $address);
        });
    }

    public function resend(Receipt $receipt, string $address, $actor): void
    {
        $this->defer($receipt, $address);
        app(AuditRecorder::class)->record($actor, 'receipt.resent', $receipt, ['receipt_id' => $receipt->id], 'Payments', 'Receipt email resent.');
    }

    private function send(Receipt $receipt, string $address): void
    {
        $oldTimeout = config('mail.mailers.smtp.timeout');
        config(['mail.mailers.smtp.timeout' => 10]);
        try {
            $statusUrl = $receipt->application_id
                ? URL::temporarySignedRoute('applications.status.signed', now()->addDays(7), ['application' => $receipt->application?->public_reference])
                : null;
            Mail::to($address)->send(new ReceiptIssuedMail(
                $receipt->number,
                app(ReceiptPdf::class)->bytes($receipt),
                $statusUrl,
            ));
            $receipt->update(['emailed_at' => now(), 'mail_failed_at' => null]);
        } catch (Throwable $exception) {
            $safe = app(RecoveryMailer::class)->scrubFailureMessage($exception->getMessage(), [$address]);
            Log::warning('Receipt email failed ['.$exception::class.']: '.$safe, ['receipt_id' => $receipt->id]);
            $receipt->update(['mail_failed_at' => now()]);
        } finally {
            config(['mail.mailers.smtp.timeout' => $oldTimeout]);
        }
    }
}
