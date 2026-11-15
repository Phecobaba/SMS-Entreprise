<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\Application;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

class ApplicationAssessment
{
    public function record(Application $application, User $actor, string $outcome, ?string $note): Application
    {
        abort_unless($actor->can('manage_admissions') || $actor->can('manage_exam_sessions'), 403);
        app(ExamSessionWorkflow::class)->assertApplication($actor, $application);

        return DB::transaction(function () use ($application, $actor, $outcome, $note): Application {
            $locked = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['assessment_outcome', 'assessment_note']);
            $locked->update([
                'assessment_outcome' => $outcome,
                'assessment_note' => filled($note) ? trim((string) $note) : null,
                'assessment_recorded_by' => $actor->id,
                'assessment_recorded_at' => now(),
            ]);
            app(AuditRecorder::class)->record($actor, 'application_assessment_recorded', $locked, [
                'before_outcome' => $before['assessment_outcome'], 'outcome' => $outcome,
                'had_previous_note' => filled($before['assessment_note']), 'has_note' => filled($note),
            ], 'Admissions', 'Application assessment outcome recorded.');

            return $locked->refresh();
        });
    }
}
