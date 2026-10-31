<?php

namespace App\Modules\Payments\Services;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\ManualPaymentSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceLifecycle
{
    public function paidKobo(Invoice $invoice): int
    {
        $invoice->loadMissing(['payment', 'partPayments.payment']);

        return (int) ($invoice->payment?->amount_kobo ?? 0)
            + (int) $invoice->partPayments->sum(fn (Invoice $part): int => (int) ($part->payment?->amount_kobo ?? 0));
    }

    public function openPartKobo(Invoice $invoice): int
    {
        $invoice->loadMissing('partPayments');

        return (int) $invoice->partPayments->where('status', 'Unpaid')->sum('amount_kobo');
    }

    public function balanceKobo(Invoice $invoice): int
    {
        if ($invoice->status === 'Voided') {
            return 0;
        }

        return max(0, (int) $invoice->amount_kobo - $this->paidKobo($invoice));
    }

    public function allocatableKobo(Invoice $invoice): int
    {
        return max(0, $this->balanceKobo($invoice) - $this->openPartKobo($invoice));
    }

    public function hasOpenParts(Invoice $invoice): bool
    {
        if ($invoice->relationLoaded('partPayments')) {
            return $invoice->partPayments->contains('status', 'Unpaid');
        }

        return $invoice->partPayments()->where('status', 'Unpaid')->exists();
    }

    public function displayState(Invoice $invoice): string
    {
        if ($invoice->status === 'Voided') {
            return 'Voided';
        }
        if ($invoice->status === 'Paid') {
            return 'Paid';
        }
        $paid = $this->paidKobo($invoice);
        if ($paid > 0) {
            return 'Part paid';
        }
        if ($this->allocatableKobo($invoice) === 0 && $this->openPartKobo($invoice) > 0) {
            return 'Whole balance allocated';
        }

        return 'Unpaid';
    }

    public function createPart(Invoice $invoice, int $amountKobo, string $dueDate, ?string $note, User $actor): Invoice
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($invoice, $amountKobo, $dueDate, $note, $actor): Invoice {
            $parent = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($parent->kind !== Invoice::KIND_TERM_FEE || $parent->parent_invoice_id || $parent->status === 'Voided') {
                throw ValidationException::withMessages(['invoice' => 'Part-payments can be created only for a term-fee invoice.']);
            }
            if ($amountKobo < 1 || $amountKobo > $this->allocatableKobo($parent)) {
                throw ValidationException::withMessages(['amount_naira' => 'The part-payment is larger than the balance still available to allocate.']);
            }

            $part = Invoice::create([
                'parent_invoice_id' => $parent->id,
                'student_id' => $parent->student_id,
                'candidate_id' => null,
                'term_id' => $parent->term_id,
                'purpose' => 'Part-payment',
                'kind' => Invoice::KIND_PART_PAYMENT,
                'creation_source' => 'part_payment',
                'notes' => filled($note) ? trim((string) $note) : null,
                'amount_kobo' => $amountKobo,
                'due_date' => $dueDate,
                'status' => 'Unpaid',
                'created_by' => $actor->id,
            ]);
            app(AuditRecorder::class)->record($actor, 'invoice.part_payment_created', $part, [
                'parent_invoice_id' => $parent->id,
                'amount_kobo' => $amountKobo,
                'due_date' => $dueDate,
                'note' => $part->notes,
            ]);

            return $part;
        });
    }

    public function voidPart(Invoice $invoice, ?string $reason, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($invoice, $reason, $actor): void {
            if ($invoice->parent_invoice_id) {
                Invoice::whereKey($invoice->parent_invoice_id)->lockForUpdate()->firstOrFail();
            }
            $part = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($part->kind !== Invoice::KIND_PART_PAYMENT || ! $part->parent_invoice_id) {
                throw ValidationException::withMessages(['invoice' => 'This is not a linked part-payment.']);
            }
            if ($part->status !== 'Unpaid' || $part->payment()->exists()
                || $part->manualSubmissions()->where('status', ManualPaymentSubmission::STATUS_AWAITING)->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Only an unpaid part-payment with no payment awaiting approval can be voided.']);
            }
            $part->attempts()->where('status', 'Pending')->update(['status' => 'Failed']);
            $note = filled($reason) ? trim((string) $reason) : null;
            $part->update(['status' => 'Voided', 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $note]);
            app(AuditRecorder::class)->record($actor, 'invoice.part_payment_voided', $part, [
                'parent_invoice_id' => $part->parent_invoice_id, 'amount_kobo' => $part->amount_kobo, 'reason' => $note,
            ]);
        });
    }

    public function addAdjustment(Invoice $invoice, string $description, int $amountKobo, string $reason, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($invoice, $description, $amountKobo, $reason, $actor): void {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->kind === Invoice::KIND_PART_PAYMENT || $locked->status === 'Voided' || $amountKobo === 0) {
                throw ValidationException::withMessages(['adjustment' => 'This invoice cannot receive that adjustment.']);
            }
            if ($this->hasAwaitingManualPayment($locked)) {
                throw ValidationException::withMessages(['adjustment' => 'Wait until the recorded bank payment is approved or rejected before adjusting this invoice.']);
            }
            $minimum = $this->paidKobo($locked) + $this->openPartKobo($locked);
            $newTotal = (int) $locked->amount_kobo + $amountKobo;
            if ($newTotal < 1 || $newTotal < $minimum) {
                throw ValidationException::withMessages(['amount_naira' => 'The adjustment cannot reduce the invoice below money already paid or allocated.']);
            }

            $locked->lines()->create([
                'fee_item_id' => null,
                'description' => trim($description),
                'line_type' => 'adjustment',
                'amount_kobo' => $amountKobo,
                'is_required' => true,
                'adjustment_reason' => trim($reason),
                'created_by' => $actor->id,
            ]);
            $locked->update(['amount_kobo' => $newTotal]);
            app(AuditRecorder::class)->record($actor, 'invoice.adjusted', $locked, [
                'description' => trim($description), 'amount_kobo' => $amountKobo,
                'reason' => trim($reason), 'new_total_kobo' => $newTotal,
            ]);
        });
    }

    public function voidInvoice(Invoice $invoice, string $reason, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($invoice, $reason, $actor): void {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->kind === Invoice::KIND_PART_PAYMENT) {
                throw ValidationException::withMessages(['invoice' => 'Void a part-payment from its own action.']);
            }
            if ($locked->status === 'Voided') {
                return;
            }
            if ($this->paidKobo($locked) > 0 || $this->hasAwaitingManualPayment($locked)) {
                throw ValidationException::withMessages(['invoice' => 'This invoice cannot be voided because money is paid or awaiting approval.']);
            }
            $parts = $locked->partPayments()->where('status', 'Unpaid')->lockForUpdate()->get();
            foreach ($parts as $part) {
                $part->attempts()->where('status', 'Pending')->update(['status' => 'Failed']);
                $part->update(['status' => 'Voided', 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => 'Parent invoice voided: '.trim($reason)]);
                app(AuditRecorder::class)->record($actor, 'invoice.part_payment_voided', $part, [
                    'parent_invoice_id' => $locked->id,
                    'amount_kobo' => $part->amount_kobo,
                    'reason' => 'Parent invoice voided: '.trim($reason),
                ]);
            }
            $locked->attempts()->where('status', 'Pending')->update(['status' => 'Failed']);
            $locked->update(['status' => 'Voided', 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => trim($reason)]);
            app(AuditRecorder::class)->record($actor, 'invoice.voided', $locked, [
                'reason' => trim($reason), 'parts_voided' => $parts->count(),
            ]);
        });
    }

    public function syncParentAfterSettlement(Invoice $invoice, User $actor): void
    {
        if (! $invoice->parent_invoice_id) {
            return;
        }
        $parent = Invoice::whereKey($invoice->parent_invoice_id)->lockForUpdate()->firstOrFail();
        if ($parent->status !== 'Paid' && $this->balanceKobo($parent) === 0) {
            $parent->update(['status' => 'Paid']);
            app(AuditRecorder::class)->record($actor, 'invoice.paid_by_parts', $parent, [
                'settling_part_invoice_id' => $invoice->id,
            ]);
        }
    }

    public function history(Invoice $invoice)
    {
        $ids = [$invoice->id];
        if (! $invoice->parent_invoice_id) {
            $ids = array_merge($ids, $invoice->partPayments()->pluck('id')->all());
        }

        return AuditLog::with('user')->where('subject_type', Invoice::class)
            ->whereIn('subject_id', $ids)->latest('created_at')->get();
    }

    private function hasAwaitingManualPayment(Invoice $invoice): bool
    {
        return $invoice->manualSubmissions()->where('status', ManualPaymentSubmission::STATUS_AWAITING)->exists()
            || $invoice->partPayments()->whereHas('manualSubmissions', fn ($query) => $query->where('status', ManualPaymentSubmission::STATUS_AWAITING))->exists();
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->can('manage_fee_schedule'), 403);
    }
}
