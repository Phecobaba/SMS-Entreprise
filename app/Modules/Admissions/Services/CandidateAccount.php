<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\AdmissionChecklistItem;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\CandidateChecklist;
use App\Modules\Admissions\Models\CandidateStatusHistory;
use App\Modules\Admissions\Models\ExamSession;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\SessionRevoker;
use App\Modules\Core\Services\TemporaryCredentials;
use App\Modules\Core\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CandidateAccount
{
    public function existsForUser(int $userId): bool
    {
        return Candidate::where('user_id', $userId)->exists();
    }

    /** @return array{eligible:Collection<int, Application>, skipped:Collection<int, array{application:Application,reason:string}>} */
    public function preview(ExamSession $session, User $actor): array
    {
        $this->authorize($actor);
        $rows = $session->applications()->with(['level', 'candidate'])->orderBy('child_surname')->orderBy('child_first_name')->get();
        $eligible = collect();
        $skipped = collect();
        foreach ($rows as $application) {
            $reason = $this->skipReason($application);
            if ($reason === null) {
                $eligible->push($application);
            } else {
                $skipped->push(['application' => $application, 'reason' => $reason]);
            }
        }

        return compact('eligible', 'skipped');
    }

    /** @param list<int> $applicationIds
     * @return array{pdf:string,created:int,skipped:array<int,array{name:string,reason:string}>}
     */
    public function createForSession(ExamSession $session, array $applicationIds, User $actor): array
    {
        $this->authorize($actor);
        $ids = collect($applicationIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        return DB::transaction(function () use ($session, $ids, $actor): array {
            ExamSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $applications = Application::where('exam_session_id', $session->id)->whereIn('id', $ids)
                ->with(['level', 'examSession'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($applications->count() === $ids->count(), 404);
            $role = Role::firstOrCreate(['name' => 'Candidate']);
            $slips = [];
            $skipped = [];
            foreach ($applications as $application) {
                $reason = $this->skipReason($application);
                if ($reason !== null) {
                    $skipped[] = ['name' => $this->name($application), 'reason' => $reason];

                    continue;
                }
                $password = app(TemporaryCredentials::class)->generate();
                $user = new User;
                $user->fill([
                    'name' => $this->name($application), 'username' => strtolower($application->username),
                    'email' => null, 'must_change_password' => false, 'status' => User::STATUS_ACTIVE,
                ]);
                $user->setRawAttributes(array_merge($user->getAttributes(), [
                    'password' => app(TemporaryCredentials::class)->hash($password), 'email_verified_at' => null,
                ]));
                $user->save();
                $user->roles()->syncWithoutDetaching([$role->id => ['school_id' => app(TenantContext::class)->id()]]);
                $candidate = Candidate::create([
                    'user_id' => $user->id, 'application_id' => $application->id,
                    'applied_level_id' => $application->applied_level_id, 'name' => $user->name,
                    'phone' => $application->guardian_phone, 'status' => 'Payment Confirmed',
                ]);
                foreach (AdmissionChecklistItem::all() as $item) {
                    CandidateChecklist::create([
                        'candidate_id' => $candidate->id,
                        'checklist_item_id' => $item->id,
                    ]);
                }
                CandidateStatusHistory::create([
                    'candidate_id' => $candidate->id, 'from_status' => null, 'to_status' => 'Payment Confirmed',
                    'changed_by' => $actor->id, 'reason' => 'Paid application account created for exam day',
                    'is_override' => false, 'changed_at' => now(),
                ]);
                $slips[] = $this->candidateSlip($application, $user, $password);
            }

            $pdf = app(CandidateSlipPdf::class)->render($slips);
            app(AuditRecorder::class)->record($actor, 'candidate_accounts_created', $session, [
                'created_count' => count($slips), 'skipped_count' => count($skipped),
            ], 'Admissions', 'Candidate exam-day accounts created and credential slips rendered.');

            return ['pdf' => $pdf, 'created' => count($slips), 'skipped' => $skipped];
        });
    }

    /** @param list<int> $candidateIds
     * @return array{pdf:string,reset:int}
     */
    public function resetForSession(ExamSession $session, array $candidateIds, User $actor): array
    {
        $this->authorize($actor);
        $ids = collect($candidateIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        return DB::transaction(function () use ($session, $ids, $actor): array {
            ExamSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $candidates = Candidate::whereIn('id', $ids)->whereHas('application', fn ($query) => $query->where('exam_session_id', $session->id))
                ->with(['user', 'application.examSession'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($candidates->count() === $ids->count(), 404);
            $slips = [];
            foreach ($candidates as $candidate) {
                $password = app(TemporaryCredentials::class)->generate();
                $candidate->user->setRawAttributes(array_merge($candidate->user->getAttributes(), [
                    'password' => app(TemporaryCredentials::class)->hash($password),
                    'must_change_password' => false,
                ]));
                $candidate->user->save();
                app(SessionRevoker::class)->all($candidate->user);
                $slips[] = $this->candidateSlip($candidate->application, $candidate->user, $password);
            }
            $pdf = app(CandidateSlipPdf::class)->render($slips);
            app(AuditRecorder::class)->record($actor, 'candidate_passwords_reset', $session, [
                'reset_count' => count($slips),
            ], 'Admissions', 'Candidate passwords reset and replacement credential slips rendered.');

            return ['pdf' => $pdf, 'reset' => count($slips)];
        });
    }

    /** @return array<string, mixed> */
    public function parentSlip(User $parent, string $password, array $children): array
    {
        return [
            'kind' => 'parent', 'school_name' => School::findOrFail(app(TenantContext::class)->id())->name,
            'name' => $parent->name, 'username' => $parent->username ?: $parent->email,
            'temporary_password' => $password, 'children' => $children,
            'sign_in_address' => route('login'), 'instruction' => 'Sign in, then choose a new password when asked.',
        ];
    }

    private function skipReason(Application $application): ?string
    {
        if ($application->status !== ApplicationWorkflow::REGISTERED) {
            return 'Payment is not confirmed';
        }
        if ($application->candidate) {
            return 'Account already created';
        }
        if ($application->level?->assessed_in_person) {
            return 'Assessed in person';
        }
        if (User::whereRaw('LOWER(username) = ?', [strtolower($application->username)])->exists()) {
            return 'Username is already in use';
        }

        return null;
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->can('manage_admissions'), 403);
    }

    private function name(Application $application): string
    {
        return trim($application->child_first_name.' '.$application->child_other_names.' '.$application->child_surname);
    }

    /** @return array<string, mixed> */
    private function candidateSlip(Application $application, User $user, string $password): array
    {
        return [
            'kind' => 'candidate', 'school_name' => School::findOrFail(app(TenantContext::class)->id())->name,
            'name' => $user->name, 'session_name' => $application->examSession->name,
            'session_date' => $application->examSession->starts_at->timezone('Africa/Lagos')->format('j F Y'),
            'session_time' => $application->examSession->starts_at->timezone('Africa/Lagos')->format('g:i a'),
            'venue' => $application->examSession->venue_note, 'username' => $user->username,
            'temporary_password' => $password, 'sign_in_address' => route('login'),
            'instruction' => 'Bring this slip on exam day and sign in only when instructed.',
        ];
    }
}
