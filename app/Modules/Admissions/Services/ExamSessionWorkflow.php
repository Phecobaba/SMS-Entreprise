<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Mail\ApplicationExamSessionMail;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ExamSession;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\RecoveryMailer;
use App\Modules\Core\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExamSessionWorkflow
{
    public function scopeApplications(Builder $query, User $actor): Builder
    {
        abort_unless($actor->can('manage_admissions') || $actor->can('manage_exam_sessions'), 403);
        if ($actor->can('manage_admissions')) {
            return $query;
        }

        $sectionIds = app(SectionAccess::class)->sectionIds($actor);

        return $query->whereHas('level', fn (Builder $level) => $level->whereIn('section_id', $sectionIds));
    }

    public function assertApplication(User $actor, Application $application): void
    {
        $this->scopeApplications(Application::whereKey($application->id), $actor)->firstOrFail();
    }

    public function scopeSessions(Builder $query, User $actor): Builder
    {
        abort_unless($actor->can('manage_exam_sessions'), 403);
        if ($actor->can('manage_admissions')) {
            return $query;
        }

        return $query->whereIn('section_id', app(SectionAccess::class)->sectionIds($actor));
    }

    public function assertSession(User $actor, ExamSession $session): void
    {
        $this->scopeSessions(ExamSession::whereKey($session->id), $actor)->firstOrFail();
    }

    public function sections(User $actor)
    {
        if ($actor->can('manage_admissions')) {
            return Section::orderBy('name')->limit(100)->get();
        }

        return Section::whereIn('id', app(SectionAccess::class)->sectionIds($actor))
            ->orderBy('name')->limit(100)->get();
    }

    public function sessionOptions(User $actor)
    {
        return $this->scopeSessions(ExamSession::with('section')->withCount('applications'), $actor)
            ->orderBy('starts_at')->limit(100)->get();
    }

    public function create(array $data, User $actor): ExamSession
    {
        $sectionId = $this->sectionId($data['section_id'] ?? null, $actor);
        $session = ExamSession::create([
            ...$data,
            'section_id' => $sectionId,
            'created_by' => $actor->id,
        ]);
        app(AuditRecorder::class)->record($actor, 'application_exam_session_created', $session, [], 'Admissions', 'Entrance exam session created.');

        return $session;
    }

    public function update(ExamSession $session, array $data, User $actor, bool $emailApplicants): ExamSession
    {
        $this->assertSession($actor, $session);
        $sectionId = $this->sectionId($data['section_id'] ?? null, $actor);
        $before = $session->only(['name', 'starts_at', 'venue_note', 'is_in_person', 'section_id']);
        $detailsChanged = $session->starts_at?->format('Y-m-d H:i:s') !== CarbonImmutable::parse($data['starts_at'])->format('Y-m-d H:i:s')
            || $session->venue_note !== $data['venue_note'];
        $session->update([...$data, 'section_id' => $sectionId]);
        app(AuditRecorder::class)->record($actor, 'application_exam_session_updated', $session, [
            'before' => $before,
            'email_applicants' => $detailsChanged && $emailApplicants,
        ], 'Admissions', 'Entrance exam session updated.');
        if ($detailsChanged && $emailApplicants) {
            $this->deferEmails($session->applications()->pluck('id')->all(), $session->id);
        }

        return $session->fresh();
    }

    public function delete(ExamSession $session, User $actor): void
    {
        $this->assertSession($actor, $session);
        $count = $session->applications()->count();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'session' => "This session cannot be deleted because {$count} application".($count === 1 ? ' is' : 's are').' assigned to it.',
            ]);
        }
        app(AuditRecorder::class)->record($actor, 'application_exam_session_deleted', $session, [], 'Admissions', 'Entrance exam session deleted.');
        $session->delete();
    }

    /** @return array{assigned:int,skipped:int} */
    public function assign(array $applicationIds, ExamSession $session, User $actor): array
    {
        $this->assertSession($actor, $session);
        $ids = collect($applicationIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $applications = $this->scopeApplications(Application::whereIn('id', $ids), $actor)->with('level')->get();
        if ($applications->count() !== $ids->count()) {
            abort(404);
        }

        $assigned = [];
        $skipped = 0;
        DB::transaction(function () use ($applications, $session, $actor, &$assigned, &$skipped): void {
            foreach ($applications as $application) {
                $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
                $this->assertAssignable($actor, $locked, $session);
                if ($locked->exam_session_id !== null) {
                    $skipped++;

                    continue;
                }
                $locked->update(['exam_session_id' => $session->id]);
                app(AuditRecorder::class)->record($actor, 'application_exam_session_assigned', $locked, [
                    'exam_session_id' => $session->id,
                ], 'Admissions', 'Applicant assigned to an entrance exam session.');
                $assigned[] = $locked->id;
            }
        });
        $this->deferEmails($assigned, $session->id);

        return ['assigned' => count($assigned), 'skipped' => $skipped];
    }

    public function move(Application $application, ExamSession $session, User $actor): bool
    {
        $this->assertApplication($actor, $application);
        $this->assertSession($actor, $session);
        $this->assertAssignable($actor, $application, $session);
        if ((int) $application->exam_session_id === (int) $session->id) {
            return false;
        }

        $previous = $application->exam_session_id;
        $application->update(['exam_session_id' => $session->id]);
        app(AuditRecorder::class)->record($actor, 'application_exam_session_moved', $application, [
            'from_exam_session_id' => $previous,
            'to_exam_session_id' => $session->id,
        ], 'Admissions', 'Applicant moved to another entrance exam session.');
        $this->deferEmails([$application->id], $session->id);

        return true;
    }

    public function remove(Application $application, User $actor): void
    {
        $this->assertApplication($actor, $application);
        if ($application->exam_session_id === null) {
            return;
        }
        $previous = $application->exam_session_id;
        $application->update(['exam_session_id' => null]);
        app(AuditRecorder::class)->record($actor, 'application_exam_session_removed', $application, [
            'from_exam_session_id' => $previous,
        ], 'Admissions', 'Applicant removed from an entrance exam session.');
    }

    private function sectionId(mixed $value, User $actor): ?int
    {
        $sectionId = filled($value) ? (int) $value : null;
        if ($actor->can('manage_admissions')) {
            if ($sectionId !== null) {
                app(SectionAccess::class)->assertSection($actor, $sectionId);
            }

            return $sectionId;
        }
        if ($sectionId === null) {
            throw ValidationException::withMessages(['section_id' => 'Choose your section.']);
        }
        app(SectionAccess::class)->assertSection($actor, $sectionId);

        return $sectionId;
    }

    private function assertAssignable(User $actor, Application $application, ExamSession $session): void
    {
        if ($application->status !== ApplicationWorkflow::REGISTERED) {
            throw ValidationException::withMessages(['applications' => 'Only paid applications can be assigned to an exam session.']);
        }
        if (! $actor->can('manage_admissions')) {
            $application->loadMissing('level');
            if ($session->section_id === null || (int) $session->section_id !== (int) $application->level?->section_id) {
                throw ValidationException::withMessages(['session' => "Choose an exam session in the applicant's section."]);
            }
        }
    }

    /** @param list<int> $applicationIds */
    private function deferEmails(array $applicationIds, int $sessionId): void
    {
        if ($applicationIds === []) {
            return;
        }
        $schoolId = app(TenantContext::class)->id();
        defer(function () use ($applicationIds, $sessionId, $schoolId): void {
            app(TenantContext::class)->set($schoolId);
            $session = ExamSession::find($sessionId);
            if (! $session) {
                return;
            }
            foreach (Application::whereIn('id', $applicationIds)->get() as $application) {
                try {
                    Mail::to($application->guardian_email)->send(new ApplicationExamSessionMail(
                        $application->child_first_name,
                        $session->name,
                        $session->starts_at->timezone('Africa/Lagos')->format('j F Y'),
                        $session->starts_at->timezone('Africa/Lagos')->format('g:i a'),
                        $session->venue_note,
                        app(ApplicationWorkflow::class)->statusUrl($application),
                    ));
                    $application->update(['exam_notified_at' => now(), 'mail_failed_at' => null]);
                } catch (Throwable $exception) {
                    $application->update(['mail_failed_at' => now()]);
                    Log::warning('Application exam session email could not be sent.', [
                        'exception_class' => $exception::class,
                        'message' => app(RecoveryMailer::class)->scrubFailureMessage($exception->getMessage(), [$application->guardian_email]),
                    ]);
                }
            }
        }, 'application-exam-session-'.implode('-', $applicationIds).'-'.$sessionId);
    }
}
