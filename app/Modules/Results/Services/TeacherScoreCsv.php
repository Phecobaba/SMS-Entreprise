<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\TeacherFacingLabel;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Results\Models\Result;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeacherScoreCsv
{
    private const EXPIRY_MINUTES = 30;

    public function template(User $actor, int $classId, int $subjectId): array
    {
        $context = $this->context($actor, $classId, $subjectId);
        $components = $this->components($context['students'], $subjectId, $context['term']->id);
        $headers = $this->headers($components);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF".$this->comment($context)."\r\n");
        fputcsv($stream, $headers, ',', '"', '');

        $results = Result::with('items')->where('subject_id', $subjectId)
            ->where('term_id', $context['term']->id)
            ->whereIn('student_id', $context['students']->pluck('id'))
            ->get()->keyBy('student_id');
        foreach ($context['students'] as $student) {
            $result = $results->get($student->id);
            $existing = $result && in_array($result->status, ['Draft', 'Rejected'], true)
                ? $result->items->pluck('score', 'assessment_component_id')
                : collect();
            $row = [
                $student->registration_number,
                $this->spreadsheetSafe($student->user?->name ?? 'Student #'.$student->id),
            ];
            foreach ($components as $component) {
                $row[] = $existing->has($component['id']) ? $this->number($existing[$component['id']]) : '';
            }
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return [
            'contents' => $csv,
            'filename' => Str::slug($context['pair_label'].' '.$context['term_label']).'-scores.csv',
        ];
    }

    public function preview(User $actor, int $classId, int $subjectId, UploadedFile $file): array
    {
        $context = $this->context($actor, $classId, $subjectId);
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        $this->cleanupExpired($actor);
        $token = Str::random(48);
        $path = 'result-score-imports/'.$actor->school_id.'/'.$token.'.csv';
        Storage::disk('local')->put($path, $contents);
        Session::put($this->sessionKey($token), [
            'owner_id' => $actor->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'term_id' => $context['term']->id,
            'path' => $path,
            'hash' => hash('sha256', $contents),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES)->timestamp,
        ]);

        return $this->parse($contents, $context) + ['token' => $token, 'expires_in_minutes' => self::EXPIRY_MINUTES];
    }

    public function apply(User $actor, int $classId, int $subjectId, string $token): int
    {
        $stage = Session::get($this->sessionKey($token));
        if (! is_array($stage)
            || (int) ($stage['owner_id'] ?? 0) !== $actor->id
            || (int) ($stage['class_id'] ?? 0) !== $classId
            || (int) ($stage['subject_id'] ?? 0) !== $subjectId) {
            throw new AuthorizationException('This score upload does not belong to you or this subject and class.');
        }

        $currentTerm = app(AcademicCalendar::class)->currentTerm();
        if (! $currentTerm || (int) $stage['term_id'] !== $currentTerm->id) {
            $this->consume($token, $stage);
            throw new AuthorizationException('This score upload was made for a different term.');
        }
        if ((int) $stage['expires_at'] < now()->timestamp || ! Storage::disk('local')->exists($stage['path'])) {
            $this->consume($token, $stage);
            throw ValidationException::withMessages(['file' => 'This score upload has expired. Upload it again.']);
        }

        $contents = Storage::disk('local')->get($stage['path']);
        if (! hash_equals((string) $stage['hash'], hash('sha256', $contents))) {
            $this->consume($token, $stage);
            throw ValidationException::withMessages(['file' => 'This staged score file changed. Upload it again.']);
        }

        $context = $this->context($actor, $classId, $subjectId);
        $report = $this->parse($contents, $context);
        if ($report['errors'] !== []) {
            $this->consume($token, $stage);
            throw ValidationException::withMessages(['file' => collect($report['errors'])->pluck('message')->implode(' ')]);
        }

        try {
            DB::transaction(function () use ($actor, $classId, $subjectId, $context, $report): void {
                foreach ($report['rows'] as $row) {
                    app(ResultWorkflow::class)->saveDraft(
                        $row['student_id'],
                        $subjectId,
                        $context['term']->id,
                        $row['scores_by_id'],
                        $actor,
                    );
                }
                app(AuditRecorder::class)->record($actor, 'teacher_score_csv_applied', null, [
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'term_id' => $context['term']->id,
                    'pupils_updated' => count($report['rows']),
                ]);
            });
        } finally {
            $this->consume($token, $stage);
        }

        return count($report['rows']);
    }

    private function context(User $actor, int $classId, int $subjectId): array
    {
        $assignment = app(ResultTeachingAssignments::class)->assignedPair($actor, $classId, $subjectId);
        if (! $assignment) {
            throw new AuthorizationException('You are not assigned to this subject and class.');
        }
        $term = app(AcademicCalendar::class)->currentTerm();
        if (! $term) {
            throw ValidationException::withMessages(['term' => 'No current term is configured.']);
        }
        $arms = ClassArm::where('class_id', $classId)->pluck('id');
        $students = Student::active()->with(['user', 'classArm'])
            ->whereIn('class_arm_id', $arms)->orderBy('id')->get();
        if ($students->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'There are no active pupils in this class.']);
        }
        if ($students->contains(fn (Student $student): bool => trim((string) $student->registration_number) === '')) {
            throw ValidationException::withMessages(['file' => 'Every pupil needs a registration number before using the score template.']);
        }
        $pairLabel = app(TeacherFacingLabel::class)->pair($assignment->subject?->name, $assignment->schoolClass?->name);
        $termLabel = trim(($term->name ?? 'Term').' '.($term->session?->name ?? ''));

        return compact('assignment', 'term', 'students', 'pairLabel', 'termLabel') + [
            'pair_label' => $pairLabel,
            'term_label' => $termLabel,
        ];
    }

    private function components(Collection $students, int $subjectId, int $termId): Collection
    {
        $result = Result::where('student_id', $students->first()->id)
            ->where('subject_id', $subjectId)->where('term_id', $termId)->first();
        $template = $result
            ? app(ResultTemplateResolver::class)->storedFor($result)
            : app(ResultTemplateResolver::class)->forClassArm($students->first()->class_arm_id);
        if (! $template) {
            throw ValidationException::withMessages(['file' => 'No result template applies to this class. Ask the school office to set one up.']);
        }

        return $template->components->map(fn ($entry): array => [
            'id' => $entry->assessment_component_id,
            'name' => $entry->component->name,
            'max' => (float) $entry->component->max_score,
        ])->values();
    }

    private function parse(string $contents, array $context): array
    {
        $errors = [];
        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            return $this->report([], [['row' => 1, 'message' => 'Use a UTF-8 CSV file.']], 0);
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $comment = rtrim((string) fgets($stream), "\r\n");
        if (! hash_equals($this->comment($context), $comment)) {
            $errors[] = ['row' => 1, 'message' => 'This file was made for a different subject, class or term.'];
        }
        $components = $this->components($context['students'], $context['assignment']->subject_id, $context['term']->id);
        $expectedHeaders = $this->headers($components);
        $headers = fgetcsv($stream, null, ',', '"', '');
        if ($headers !== $expectedHeaders) {
            $errors[] = ['row' => 2, 'message' => 'Use the roster template unchanged. The score columns do not match this class template.'];
        }

        $rows = [];
        $seen = [];
        $dataRows = 0;
        $line = 2;
        while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $line++;
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            $dataRows++;
            if (count($values) !== count($expectedHeaders)) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.' has the wrong number of columns.'];

                continue;
            }
            $scores = array_slice($values, 2);
            $filled = array_filter($scores, fn ($score): bool => trim((string) $score) !== '');
            if ($filled === []) {
                continue;
            }
            if (count($filled) !== count($components)) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': fill every component or leave the row blank.'];

                continue;
            }
            $number = mb_strtoupper(trim((string) ($values[0] ?? '')));
            if ($number === '') {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': registration number is required.'];

                continue;
            }
            if (isset($seen[$number])) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': registration number '.$number.' also appears on row '.$seen[$number].'.'];

                continue;
            }
            $seen[$number] = $line;
            $student = Student::with(['user', 'classArm'])->whereRaw('UPPER(registration_number) = ?', [$number])->first();
            if (! $student) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': registration number '.$number.' was not found.'];

                continue;
            }
            if ($student->status !== Student::STATUS_ACTIVE) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': '.$number.' is not an active pupil.'];

                continue;
            }
            if ((int) $student->classArm?->class_id !== (int) $context['assignment']->class_id) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': '.$number.' belongs to another class.'];

                continue;
            }
            $existing = Result::where('student_id', $student->id)
                ->where('subject_id', $context['assignment']->subject_id)
                ->where('term_id', $context['term']->id)->first();
            if ($existing && in_array($existing->status, ['Submitted', 'Approved'], true)) {
                $errors[] = ['row' => $line, 'message' => 'Row '.$line.': this pupil\'s result is '.$existing->status.' and cannot be changed.'];

                continue;
            }

            $byId = [];
            $display = [];
            foreach ($components as $index => $component) {
                $raw = trim((string) $scores[$index]);
                if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > $component['max']) {
                    $errors[] = ['row' => $line, 'message' => 'Row '.$line.': '.$component['name'].' must be between 0 and '.$this->number($component['max']).'.'];

                    continue 2;
                }
                $byId[$component['id']] = (float) $raw;
                $display[$component['name']] = $this->number($raw);
            }
            $rows[] = [
                'row' => $line,
                'student_id' => $student->id,
                'registration_number' => $student->registration_number,
                'student_name' => $student->user?->name ?? 'Student #'.$student->id,
                'scores' => $display,
                'scores_by_id' => $byId,
            ];
        }
        fclose($stream);
        if ($dataRows > $context['students']->count()) {
            $errors[] = ['row' => 3, 'message' => 'The file has more pupil rows than this class. Download a fresh roster template.'];
        }

        return $this->report($rows, $errors, $dataRows);
    }

    private function report(array $rows, array $errors, int $dataRows): array
    {
        return [
            'rows' => $rows,
            'errors' => $errors,
            'row_count' => $dataRows,
            'ready_count' => count($rows),
            'skipped_count' => max(0, $dataRows - count($rows) - count($errors)),
            'error_count' => count($errors),
        ];
    }

    private function headers(Collection $components): array
    {
        return ['registration_number', 'student_name', ...$components->map(
            fn (array $component): string => $component['name'].' ('.$this->number($component['max']).')'
        )->all()];
    }

    private function comment(array $context): string
    {
        return '# Scores for '.$context['pair_label'].' · '.$context['term_label'];
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }

    private function number(mixed $value): string
    {
        $number = (float) $value;

        return fmod($number, 1.0) === 0.0
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function sessionKey(string $token): string
    {
        return 'teacher_score_csv.'.$token;
    }

    private function consume(string $token, array $stage): void
    {
        if (isset($stage['path'])) {
            Storage::disk('local')->delete($stage['path']);
        }
        Session::forget($this->sessionKey($token));
    }

    private function cleanupExpired(User $actor): void
    {
        $cutoff = now()->subMinutes(self::EXPIRY_MINUTES)->timestamp;
        foreach (Storage::disk('local')->files('result-score-imports/'.$actor->school_id) as $path) {
            if (Storage::disk('local')->lastModified($path) < $cutoff) {
                Storage::disk('local')->delete($path);
            }
        }
        foreach ((array) Session::get('teacher_score_csv', []) as $token => $stage) {
            if (is_array($stage) && (int) ($stage['owner_id'] ?? 0) === $actor->id && (int) ($stage['expires_at'] ?? 0) < now()->timestamp) {
                $this->consume((string) $token, $stage);
            }
        }
    }
}
