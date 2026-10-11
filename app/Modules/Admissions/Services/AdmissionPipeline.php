<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\AdmissionChecklistItem;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\CandidateChecklist;
use App\Modules\Admissions\Models\CandidateStatusHistory;
use App\Modules\Admissions\Models\Student;
use App\Modules\CBT\Services\ExamWorkflow;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionPipeline
{
    public function candidateForUser(User $user): ?Candidate
    {
        return Candidate::where('user_id', $user->id)->first();
    }

    public function assertCandidateMaySit(Candidate $candidate): void
    {
        if (! in_array($candidate->status, ['Payment Confirmed', 'Exam Taken'], true)) {
            throw ValidationException::withMessages(['candidate' => 'Entrance exams require confirmed registration payment.']);
        }
    }

    public function recordCbtStart(int $candidateId): void
    {
        DB::transaction(function () use ($candidateId): void {
            $candidate = Candidate::whereKey($candidateId)->lockForUpdate()->firstOrFail();
            if ($candidate->status === 'Exam Taken') {
                return;
            }
            $this->transition($candidate, 'Payment Confirmed', 'Exam Taken', null, 'Entrance CBT started');
        });
    }

    public function recordVerifiedCbtOutcome(int $candidateId, bool $passed, int $examId, float $percentage): void
    {
        DB::transaction(function () use ($candidateId, $passed, $examId, $percentage): void {
            $candidate = Candidate::whereKey($candidateId)->lockForUpdate()->firstOrFail();
            if (in_array($candidate->status, ['Passed', 'Failed'], true)) {
                return;
            }
            if ($candidate->status !== 'Exam Taken') {
                return;
            }
            $this->transition($candidate, 'Exam Taken', $passed ? 'Passed' : 'Failed', null,
                'Verified CBT exam #'.$examId.' score '.number_format($percentage, 2).'%');
        });
    }

    public function register(array $data): Candidate
    {
        return DB::transaction(function () use ($data): Candidate {
            if (! empty($data['applied_level_id'])) {
                AcademicLevel::whereNotNull('section_id')->findOrFail($data['applied_level_id']);
            }
            $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password'], 'status' => 'active']);
            $role = Role::firstOrCreate(['name' => 'Candidate']);
            $user->roles()->syncWithoutDetaching([$role->id]);
            $candidate = Candidate::create(['user_id' => $user->id, 'applied_level_id' => $data['applied_level_id'] ?? null, 'name' => $data['name'], 'phone' => $data['phone'], 'status' => 'Registered']);
            $this->record($candidate, null, 'Registered', null, 'Candidate registered');
            foreach (AdmissionChecklistItem::all() as $item) {
                CandidateChecklist::create(['candidate_id' => $candidate->id, 'checklist_item_id' => $item->id]);
            }
            $this->transition($candidate, 'Registered', 'Payment Pending', null, 'Awaiting registration payment confirmation');

            return $candidate;
        });
    }

    public function confirmPayment(Candidate $candidate, ?User $actor): void
    {
        DB::transaction(function () use ($candidate, $actor): void {
            $candidate = $this->locked($candidate);
            $paid = Invoice::where('candidate_id', $candidate->id)->where('purpose', 'Registration')
                ->where('status', 'Paid')->whereHas('payment')->exists();
            if (! $paid) {
                throw ValidationException::withMessages(['payment' => 'A settled registration invoice is required before admission can continue.']);
            }
            $this->transition($candidate, 'Payment Pending', 'Payment Confirmed', $actor, 'Registration invoice payment verified');
        });
    }

    public function recordExamTaken(Candidate $candidate, User $actor): void
    {
        DB::transaction(function () use ($candidate, $actor): void {
            $this->transition($this->locked($candidate), 'Payment Confirmed', 'Exam Taken', $actor, 'Entrance exam attendance recorded manually');
        });
    }

    // Phase 1 fallback remains available only until a published entrance CBT exists.
    public function recordExamOutcome(Candidate $candidate, User $actor, string $outcome): void
    {
        if (app(ExamWorkflow::class)->hasPublishedEntranceExam()) {
            throw ValidationException::withMessages(['outcome' => 'A published entrance CBT is available; use its verified result instead of a manual outcome.']);
        }
        if (! in_array($outcome, ['Passed', 'Failed'], true)) {
            throw ValidationException::withMessages(['outcome' => 'Select Passed or Failed.']);
        }
        DB::transaction(function () use ($candidate, $actor, $outcome): void {
            $this->transition($this->locked($candidate), 'Exam Taken', $outcome, $actor, 'Manual entrance-exam outcome; CBT not integrated');
        });
    }

    public function addChecklistItem(string $label): AdmissionChecklistItem
    {
        return DB::transaction(function () use ($label): AdmissionChecklistItem {
            $item = AdmissionChecklistItem::create(['label' => $label]);
            foreach (Candidate::where('status', '!=', 'Converted to Student')->cursor() as $candidate) {
                CandidateChecklist::create(['candidate_id' => $candidate->id, 'checklist_item_id' => $item->id]);
            }

            return $item;
        });
    }

    public function setChecklist(Candidate $candidate, AdmissionChecklistItem $item, User $actor, bool $complete): void
    {
        DB::transaction(function () use ($candidate, $item, $actor, $complete): void {
            $candidate = $this->locked($candidate);
            if ($candidate->status === 'Converted to Student') {
                throw ValidationException::withMessages(['checklist' => 'A converted candidate’s checklist cannot be changed.']);
            }
            $entry = CandidateChecklist::firstOrCreate(['candidate_id' => $candidate->id, 'checklist_item_id' => $item->id]);
            $entry->update(['is_complete' => $complete, 'completed_by' => $complete ? $actor->id : null, 'completed_at' => $complete ? now() : null]);
        });
    }

    public function extendOffer(Candidate $candidate, User $actor, string $deadline): AdmissionOffer
    {
        return DB::transaction(function () use ($candidate, $actor, $deadline): AdmissionOffer {
            $candidate = $this->locked($candidate);
            $this->transition($candidate, 'Passed', 'Offer Extended', $actor, 'Admission offer extended');

            return AdmissionOffer::create(['candidate_id' => $candidate->id, 'offered_by' => $actor->id, 'offer_date' => today(), 'response_deadline' => $deadline, 'status' => 'Pending', 'deposit_required' => false, 'deposit_paid' => false]);
        });
    }

    public function respond(Candidate $candidate, string $response, ?User $actor = null): void
    {
        if (! in_array($response, ['Accepted', 'Declined'], true)) {
            throw ValidationException::withMessages(['response' => 'Select Accept or Decline.']);
        }
        $expired = DB::transaction(function () use ($candidate, $response, $actor): bool {
            $candidate = $this->locked($candidate);
            $offer = AdmissionOffer::where('candidate_id', $candidate->id)->lockForUpdate()->firstOrFail();
            if ($offer->status !== 'Pending') {
                throw ValidationException::withMessages(['offer' => 'This offer has already been answered.']);
            }
            if ($offer->response_deadline->isBefore(today())) {
                $this->lapseLocked($candidate, $offer);

                return true;
            }
            $this->transition($candidate, 'Offer Extended', 'Offer '.$response, $actor, 'Candidate '.$response.' the offer');
            $offer->update(['status' => $response]);

            return false;
        });
        if ($expired) {
            throw ValidationException::withMessages(['offer' => 'The offer deadline has passed.']);
        }
    }

    public function lapse(Candidate $candidate): void
    {
        DB::transaction(function () use ($candidate): void {
            $candidate = $this->locked($candidate);
            $offer = AdmissionOffer::where('candidate_id', $candidate->id)->lockForUpdate()->firstOrFail();
            if ($offer->status !== 'Pending' || ! $offer->response_deadline->isBefore(today())) {
                throw ValidationException::withMessages(['offer' => 'Only an unanswered, expired offer can lapse.']);
            }
            $this->lapseLocked($candidate, $offer);
        });
    }

    public function convert(Candidate $candidate, User $actor, int $armId, bool $override = false, ?string $reason = null): Student
    {
        app(ClassArmDirectory::class)->assertExists($armId);
        $destinationSection = app(SectionAccess::class)->sectionForArm($armId)?->id;
        $appliedSection = $candidate->appliedLevel?->section_id;
        if ($appliedSection && $destinationSection !== $appliedSection) {
            throw ValidationException::withMessages(['class_arm_id' => 'Choose a class arm in the candidate’s applied section.']);
        }

        return DB::transaction(function () use ($candidate, $actor, $armId, $override, $reason): Student {
            $candidate = $this->locked($candidate);
            if ($candidate->status !== 'Offer Accepted') {
                throw ValidationException::withMessages(['candidate' => 'Only an accepted offer can be converted.']);
            }
            $itemCount = AdmissionChecklistItem::count();
            $completeCount = CandidateChecklist::where('candidate_id', $candidate->id)->where('is_complete', true)->count();
            if ($itemCount === 0 || $completeCount !== $itemCount) {
                if (! $override) {
                    throw ValidationException::withMessages(['checklist' => 'Complete every admission checklist item before conversion, or use a logged admin override.']);
                }
                if (! $actor->can('override_admission_checklist')) {
                    throw ValidationException::withMessages(['override' => 'You do not have permission to override the admission checklist.']);
                }
                if (! is_string($reason) || trim($reason) === '') {
                    throw ValidationException::withMessages(['override_reason' => 'Explain why the incomplete checklist is being overridden.']);
                }
            } else {
                $override = false;
            }
            $student = Student::create(['user_id' => $candidate->user_id, 'class_arm_id' => $armId, 'candidate_id' => $candidate->id, 'admission_date' => today()]);
            $role = Role::firstOrCreate(['name' => 'Student']);
            User::findOrFail($candidate->user_id)->roles()->syncWithoutDetaching([$role->id]);
            $this->transition($candidate, 'Offer Accepted', 'Converted to Student', $actor, $override ? trim($reason) : 'Checklist complete', $override);

            return $student;
        });
    }

    private function locked(Candidate $candidate): Candidate
    {
        return Candidate::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
    }

    private function transition(Candidate $candidate, string $from, string $to, ?User $actor, string $reason, bool $override = false): void
    {
        if ($candidate->status !== $from) {
            throw ValidationException::withMessages(['candidate' => "This action requires status {$from}; current status is {$candidate->status}."]);
        }
        $candidate->update(['status' => $to]);
        $this->record($candidate, $from, $to, $actor, $reason, $override);
    }

    private function record(Candidate $candidate, ?string $from, string $to, ?User $actor, string $reason, bool $override = false): void
    {
        CandidateStatusHistory::create(['candidate_id' => $candidate->id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $actor?->id, 'reason' => $reason, 'is_override' => $override, 'changed_at' => now()]);
    }

    private function lapseLocked(Candidate $candidate, AdmissionOffer $offer): void
    {
        $this->transition($candidate, 'Offer Extended', 'Offer Lapsed', null, 'Offer response deadline passed');
        $offer->update(['status' => 'Lapsed']);
    }
}
