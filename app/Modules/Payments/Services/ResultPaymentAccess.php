<?php

namespace App\Modules\Payments\Services;

use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Results\Models\ResultCard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class ResultPaymentAccess
{
    public function unpaidInvoice(ResultCard $card): ?Invoice
    {
        $invoice = Invoice::where('student_id', $card->student_id)->where('term_id', $card->term_id)
            ->where('purpose', 'Tuition')->where('status', 'Unpaid')->latest('id')->first();

        return $invoice && PaymentAttempt::where('invoice_id', $invoice->id)->latest('id')->value('status') !== 'Pending'
            ? $invoice : null;
    }

    public function unpaidForTerms(int $studentId, array $termIds): Collection
    {
        return Invoice::with('attempts')->where('student_id', $studentId)->where('purpose', 'Tuition')
            ->where('status', 'Unpaid')->whereIn('term_id', $termIds)
            ->latest('id')->get()->unique('term_id')
            ->filter(fn (Invoice $invoice) => $invoice->attempts->sortByDesc('id')->first()?->status !== 'Pending')
            ->keyBy('term_id');
    }

    public function authorize(ResultCard $card, User $viewer): void
    {
        $isStudent = app(StudentDirectory::class)->belongsToUser($card->student_id, $viewer);
        $isParent = app(ParentAccountDirectory::class)->isWard($card->student_id, $viewer->id);
        if (! $isStudent && ! $isParent) {
            throw new AuthorizationException('This result does not belong to you or your ward.');
        }
        $rule = SchoolSetting::first()?->result_access_payment_rule ?? 'TermInvoicePaid';
        if ($rule === 'None') {
            return;
        }
        if ($rule !== 'TermInvoicePaid' || ! Invoice::where('student_id', $card->student_id)
            ->where('term_id', $card->term_id)->where('purpose', 'Tuition')
            ->where('status', 'Paid')->whereHas('payment')->exists()) {
            throw new AuthorizationException('The term tuition invoice must be paid before this result is available.');
        }
    }

    public function authorizeAttendance(int $studentId, User $viewer): void
    {
        $this->authorizeOwnership($studentId, $viewer);
        $rule = SchoolSetting::first()?->result_access_payment_rule ?? 'TermInvoicePaid';
        if ($rule === 'None') {
            return;
        }
        $latest = Invoice::where('student_id', $studentId)->where('purpose', 'Tuition')
            ->latest('id')->first();
        if ($rule !== 'TermInvoicePaid' || ! $latest || $latest->status !== 'Paid' || ! $latest->payment()->exists()) {
            throw new AuthorizationException('The latest tuition invoice must be paid before attendance is available.');
        }
    }

    public function authorizeOwnership(int $studentId, User $viewer): void
    {
        if (! app(StudentDirectory::class)->belongsToUser($studentId, $viewer)
            && ! app(ParentAccountDirectory::class)->isWard($studentId, $viewer->id)) {
            throw new AuthorizationException('This student record does not belong to you or your ward.');
        }
    }
}
