<?php

namespace App\Modules\Payments\Services;

use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManualPaymentService
{
    public const PHOTO_MAX_KILOBYTES = 3072;

    /** @return array{submission: ManualPaymentSubmission, approved: bool} */
    public function record(Invoice $invoice, array $data, User $actor, ?UploadedFile $photo = null): array
    {
        abort_unless($actor->can('manage_payments'), 403);
        $key = $this->normaliseTeller((string) $data['teller_number']);
        if ($key === '') {
            throw ValidationException::withMessages(['teller_number' => 'Enter a valid teller number.']);
        }

        $storedPath = null;
        try {
            return DB::transaction(function () use ($invoice, $data, $actor, $photo, $key, &$storedPath): array {
                $locked = Invoice::with(['student.user', 'candidate'])->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                $this->assertInvoiceCanBeRecorded($locked, (int) $data['amount_kobo']);
                $this->assertTellerAvailable($key);

                try {
                    $submission = ManualPaymentSubmission::create([
                        'invoice_id' => $locked->id,
                        'bank' => trim((string) $data['bank']),
                        'teller_number' => trim((string) $data['teller_number']),
                        'active_teller_key' => $key,
                        'paid_on' => $data['paid_on'],
                        'amount_kobo' => (int) $data['amount_kobo'],
                        'recorded_by' => $actor->id,
                        'status' => ManualPaymentSubmission::STATUS_AWAITING,
                    ]);
                } catch (QueryException $exception) {
                    if ($this->isUniqueViolation($exception)) {
                        $this->assertTellerAvailable($key);
                    }
                    throw $exception;
                }

                if ($photo) {
                    $extension = match ($photo->getMimeType()) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'application/pdf' => 'pdf',
                        default => throw ValidationException::withMessages(['photo' => 'The teller photo must be a JPG, PNG or PDF.']),
                    };
                    $storedPath = $photo->storeAs('manual-payment-slips', Str::uuid().'.'.$extension, 'local');
                    if (! $storedPath) {
                        throw ValidationException::withMessages(['photo' => 'The teller photo could not be stored. Please try again.']);
                    }
                    $submission->update(['photo_path' => $storedPath]);
                }

                app(AuditRecorder::class)->record($actor, 'manual_payment.recorded', $submission, [
                    'invoice_id' => $locked->id,
                    'amount_kobo' => $submission->amount_kobo,
                    'status' => $submission->status,
                ]);

                $oneStep = (bool) (SchoolSetting::query()->lockForUpdate()->first()?->one_step_payment_approval ?? false);
                if ($oneStep) {
                    $this->approveLocked($submission, $actor, true);
                }

                return ['submission' => $submission->fresh(), 'approved' => $oneStep];
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    /** @return array{handled: bool, submission: ManualPaymentSubmission, payment: mixed} */
    public function approve(ManualPaymentSubmission $submission, User $actor): array
    {
        abort_unless($actor->can('approve_payments'), 403);

        return DB::transaction(function () use ($submission, $actor): array {
            $locked = ManualPaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ManualPaymentSubmission::STATUS_AWAITING) {
                return ['handled' => true, 'submission' => $locked, 'payment' => $locked->invoice->payment];
            }

            return ['handled' => false, 'submission' => $locked, 'payment' => $this->approveLocked($locked, $actor, false)];
        });
    }

    public function reject(ManualPaymentSubmission $submission, User $actor, string $reason): bool
    {
        abort_unless($actor->can('approve_payments'), 403);

        return DB::transaction(function () use ($submission, $actor, $reason): bool {
            $locked = ManualPaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ManualPaymentSubmission::STATUS_AWAITING) {
                return false;
            }
            $locked->update([
                'status' => ManualPaymentSubmission::STATUS_REJECTED,
                'active_teller_key' => null,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'rejection_reason' => trim($reason),
            ]);
            app(AuditRecorder::class)->record($actor, 'manual_payment.rejected', $locked, [
                'invoice_id' => $locked->invoice_id, 'reason' => trim($reason),
            ]);
            app(InAppNotifications::class)->send(
                [$locked->recorded_by],
                'Bank payment rejected',
                'The bank payment you recorded was rejected: '.trim($reason),
                route('payments.approvals.index', absolute: false),
            );

            return true;
        });
    }

    public function setOneStep(bool $enabled, User $actor): void
    {
        abort_unless($actor->can('approve_payments'), 403);

        DB::transaction(function () use ($enabled, $actor): void {
            $settings = SchoolSetting::query()->lockForUpdate()->firstOrCreate([]);
            $before = (bool) $settings->one_step_payment_approval;
            if ($before === $enabled) {
                return;
            }
            $settings->update(['one_step_payment_approval' => $enabled]);
            app(AuditRecorder::class)->record($actor, 'manual_payment.one_step_changed', $settings, [
                'from' => $before, 'to' => $enabled,
            ]);

            $adminIds = User::whereKeyNot($actor->id)
                ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'approve_payments'))
                ->pluck('id')->all();
            app(InAppNotifications::class)->send(
                $adminIds,
                'Manual payment approval changed',
                'One-step approval was turned '.($enabled ? 'on' : 'off').' by '.$actor->name.'.',
                route('payments.approvals.index', absolute: false),
            );
        });
    }

    private function approveLocked(ManualPaymentSubmission $submission, User $actor, bool $automatic)
    {
        $invoice = Invoice::whereKey($submission->invoice_id)->lockForUpdate()->firstOrFail();
        $payment = app(PaymentLedger::class)->settleManualSubmission($invoice, $submission->amount_kobo, $actor);
        $submission->update([
            'status' => ManualPaymentSubmission::STATUS_APPROVED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'rejection_reason' => null,
        ]);
        app(AuditRecorder::class)->record(
            $actor,
            $automatic ? 'manual_payment.auto_approved' : 'manual_payment.approved',
            $submission,
            ['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'amount_kobo' => $payment->amount_kobo],
        );

        return $payment;
    }

    private function assertInvoiceCanBeRecorded(Invoice $invoice, int $amountKobo): void
    {
        if (! in_array($invoice->kind, [Invoice::KIND_TERM_FEE, Invoice::KIND_PART_PAYMENT, Invoice::KIND_ONE_OFF], true)) {
            throw ValidationException::withMessages(['invoice' => 'Bank payments can be recorded only for a term fee, part payment or one-off invoice.']);
        }
        if ($invoice->status !== 'Unpaid' || $invoice->payment()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'This invoice is already paid or unavailable.']);
        }
        if ($invoice->manualSubmissions()->where('status', ManualPaymentSubmission::STATUS_AWAITING)->exists()) {
            throw ValidationException::withMessages(['invoice' => 'A bank payment is already awaiting approval for this invoice.']);
        }
        if ($invoice->kind === Invoice::KIND_TERM_FEE && app(InvoiceLifecycle::class)->hasOpenParts($invoice)) {
            throw ValidationException::withMessages(['invoice' => 'Record payment against the open part-payment, or void it before collecting the whole remaining balance.']);
        }
        if ($amountKobo !== app(InvoiceLifecycle::class)->balanceKobo($invoice)) {
            throw ValidationException::withMessages(['amount_naira' => 'The amount must equal the invoice balance exactly.']);
        }
    }

    private function assertTellerAvailable(string $key): void
    {
        $earlier = ManualPaymentSubmission::with(['invoice.student.user', 'invoice.candidate'])
            ->where('active_teller_key', $key)->first();
        if (! $earlier) {
            return;
        }
        $name = $earlier->invoice->student?->user?->name ?? $earlier->invoice->candidate?->name ?? 'another payer';
        throw ValidationException::withMessages([
            'teller_number' => 'This teller number was already used on '.$earlier->paid_on->format('j M Y').' for '.$name.'.',
        ]);
    }

    private function normaliseTeller(string $number): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(trim($number))) ?? '';
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)
            || str_contains(Str::lower($exception->getMessage()), 'unique');
    }
}
