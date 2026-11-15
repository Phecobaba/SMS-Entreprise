<?php

namespace App\Modules\CBT\Services;

use App\Modules\CBT\Models\Exam;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CbtQuestionCsv
{
    private const EXPIRY_MINUTES = 30;

    private const MAX_ROWS = 200;

    private const HEADERS = ['Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Option F', 'Correct Answer'];

    private const LEGACY_MARK_HEADERS = [
        'Question', 'Option A', 'Marks for A', 'Option B', 'Marks for B', 'Option C', 'Marks for C',
        'Option D', 'Marks for D', 'Option E', 'Marks for E', 'Option F', 'Marks for F',
    ];

    private const LEGACY_SCORE_HEADERS = [
        'Question', 'Option A', 'Option A Score', 'Option B', 'Option B Score', 'Option C', 'Option C Score',
        'Option D', 'Option D Score', 'Option E', 'Option E Score', 'Option F', 'Option F Score',
    ];

    public function __construct(private readonly ExamWorkflow $workflow) {}

    public function template(User $actor, int $examId): array
    {
        $exam = $this->eligibleExam($actor, $examId);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF# EXAMPLES BELOW — delete all three example rows before adding your questions.\r\n");
        fputcsv($stream, self::HEADERS, ',', '"', '');
        fputcsv($stream, ['EXAMPLE — delete: What is 2 + 2?', 'Three', 'Four', 'Five', '', '', '', 'B'], ',', '"', '');
        fputcsv($stream, ['EXAMPLE — delete: Which number is even?', 'Three', 'Four', 'Five', '', '', '', 'B=3'], ',', '"', '');
        fputcsv($stream, ['EXAMPLE — delete: Choose the vowels', 'A', 'B', 'E', 'C', '', '', 'A=2, C=1'], ',', '"', '');
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return [
            'contents' => $contents,
            'filename' => Str::slug($exam->title).'-question-template.csv',
        ];
    }

    public function preview(User $actor, int $examId, UploadedFile $file): array
    {
        $exam = $this->eligibleExam($actor, $examId);
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        $this->cleanupExpired($actor);
        $this->consumeCurrentForExam($actor, $exam->id);
        $token = Str::random(48);
        $path = 'cbt-question-imports/'.$actor->school_id.'/'.$token.'.csv';
        Storage::disk('local')->put($path, $contents);
        $stages = $this->stages();
        $stages[$token] = [
            'owner_id' => $actor->id,
            'exam_id' => $exam->id,
            'path' => $path,
            'hash' => hash('sha256', $contents),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES)->timestamp,
        ];
        Session::put('cbt_question_csv', $stages);

        return $this->parse($contents, $exam) + [
            'token' => $token,
            'expires_in_minutes' => self::EXPIRY_MINUTES,
        ];
    }

    public function apply(User $actor, int $examId, string $token): int
    {
        $stage = $this->stageFor($actor, $examId, $token);
        if ((int) $stage['expires_at'] < now()->timestamp || ! Storage::disk('local')->exists($stage['path'])) {
            $this->consume($token, $stage);
            throw ValidationException::withMessages(['file' => 'This question upload has expired. Upload it again.']);
        }
        $contents = Storage::disk('local')->get($stage['path']);
        if (! hash_equals((string) $stage['hash'], hash('sha256', $contents))) {
            $this->consume($token, $stage);
            throw ValidationException::withMessages(['file' => 'This staged question file changed. Upload it again.']);
        }

        try {
            $count = DB::transaction(function () use ($actor, $examId, $contents): int {
                $exam = $this->eligibleExam($actor, $examId);
                $locked = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
                $this->assertMutable($locked);
                $report = $this->parse($contents, $locked);
                if ($report['error_count'] > 0) {
                    throw ValidationException::withMessages([
                        'file' => collect($report['errors'])->pluck('message')->implode(' '),
                    ]);
                }
                $existing = $locked->questions()->count();
                if ($existing + count($report['rows']) > self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'An exam can have at most 200 questions.']);
                }
                foreach ($report['rows'] as $row) {
                    $this->workflow->addQuestion(
                        $locked,
                        $row['text'],
                        1,
                        null,
                        $row['option_texts'],
                        $row['correct_indexes'],
                        $actor,
                        $row['option_marks'],
                    );
                }
                app(AuditRecorder::class)->record($actor, 'cbt_question_csv_applied', $locked, [
                    'questions_added' => count($report['rows']),
                    'total_marks_added' => $report['total_marks'],
                ], 'CBT', count($report['rows']).' questions uploaded to '.$locked->title);

                return count($report['rows']);
            });
        } finally {
            $this->consume($token, $stage);
        }

        return $count;
    }

    public function cancel(User $actor, int $examId, string $token): void
    {
        $this->eligibleExam($actor, $examId);
        $stage = $this->stageFor($actor, $examId, $token);
        $this->consume($token, $stage);
    }

    public function cleanupExpiredFiles(): int
    {
        $deleted = 0;
        $cutoff = now()->subMinutes(self::EXPIRY_MINUTES)->timestamp;
        foreach (Storage::disk('local')->allFiles('cbt-question-imports') as $path) {
            if (Storage::disk('local')->lastModified($path) < $cutoff) {
                Storage::disk('local')->delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function parse(string $contents, Exam $exam): array
    {
        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            return $this->report([], [['row' => 1, 'column' => 'File', 'message' => 'Row 1, File: use a UTF-8 CSV file.']], $exam);
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $headers = null;
        $layout = null;
        $rows = [];
        $errors = [];
        $recordNumber = 0;
        $dataRows = 0;
        while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $recordNumber++;
            if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                continue;
            }
            $first = ltrim((string) ($values[0] ?? ''), "\xEF\xBB\xBF \t");
            if (str_starts_with($first, '#')) {
                continue;
            }
            if ($headers === null) {
                $headers = $values;
                $layout = $this->layout($headers);
                if ($layout === null) {
                    $errors[] = $this->error($recordNumber, 'Headings', $this->mixedLayout($headers)
                        ? 'Do not mix CBT question CSV layouts. Use only Correct Answer, Marks for ..., or Option ... Score headings.'
                        : 'Expected these headings in order: '.implode(', ', self::HEADERS).'.');
                }

                continue;
            }

            $dataRows++;
            if ($dataRows > self::MAX_ROWS) {
                $errors[] = $this->error($recordNumber, 'Question', 'A file can contain at most 200 data rows.');

                continue;
            }
            $expectedColumns = match ($layout) {
                'new' => count(self::HEADERS),
                'legacy_marks' => count(self::LEGACY_MARK_HEADERS),
                'legacy_scores' => count(self::LEGACY_SCORE_HEADERS),
                default => null,
            };
            if ($expectedColumns === null || count($values) !== $expectedColumns) {
                $errors[] = $this->error($recordNumber, 'Row', 'The row has the wrong number of columns.');

                continue;
            }
            if ($layout === 'new') {
                $this->parseNewRow($values, $recordNumber, $rows, $errors, $exam);
            } else {
                $this->parseLegacyRow($values, $recordNumber, $rows, $errors);
            }
        }
        fclose($stream);
        if ($headers === null) {
            $errors[] = $this->error(1, 'Headings', 'The heading row is missing. Expected: '.implode(', ', self::HEADERS).'.');
        }
        if ($rows === [] && $errors === []) {
            $errors[] = $this->error($recordNumber + 1, 'Question', 'Add at least one question row.');
        }

        return $this->report($errors === [] ? $rows : [], $errors, $exam);
    }

    private function parseLegacyRow(array $values, int $rowNumber, array &$rows, array &$errors): void
    {
        $startErrors = count($errors);
        $question = trim((string) $values[0]);
        if ($question === '') {
            $errors[] = $this->error($rowNumber, 'Question', 'Question text is required.');
        } elseif (mb_strlen($question) > 1000) {
            $errors[] = $this->error($rowNumber, 'Question', 'Question text may not exceed 1,000 characters.');
        }

        $letters = range('A', 'F');
        $optionTexts = [];
        $correctIndexes = [];
        $optionMarks = [];
        foreach ($letters as $offset => $letter) {
            $text = trim((string) $values[1 + ($offset * 2)]);
            $mark = trim((string) $values[2 + ($offset * 2)]);
            if ($text === '') {
                if ($mark !== '') {
                    $errors[] = $this->error($rowNumber, 'Marks for '.$letter, 'A mark cannot be supplied when Option '.$letter.' is empty.');
                }

                continue;
            }
            if (mb_strlen($text) > 300) {
                $errors[] = $this->error($rowNumber, 'Option '.$letter, 'Option text may not exceed 300 characters.');
            }
            $index = count($optionTexts);
            $optionTexts[] = $text;
            if ($mark !== '') {
                if (preg_match('/^[1-9][0-9]*$/', $mark) !== 1 || (int) $mark > 65535) {
                    $errors[] = $this->error($rowNumber, 'Marks for '.$letter, 'Enter a whole number of 1 or more, or leave it blank.');
                } else {
                    $correctIndexes[] = $index;
                    $optionMarks[$index] = (int) $mark;
                }
            }
        }
        if (count($optionTexts) < 2) {
            $errors[] = $this->error($rowNumber, 'Option A', 'A question needs at least two filled options.');
        }
        if ($correctIndexes === []) {
            $errors[] = $this->error($rowNumber, 'Marks for A', 'At least one filled option needs a mark.');
        } elseif (count($correctIndexes) === count($optionTexts)) {
            $errors[] = $this->error($rowNumber, 'Marks for A', 'At least one filled option must have a blank mark.');
        }
        if (count($errors) !== $startErrors) {
            return;
        }

        try {
            $validated = $this->workflow->validateQuestionInput($question, $optionTexts, $correctIndexes, $optionMarks, 1);
        } catch (ValidationException $exception) {
            $errors[] = $this->error($rowNumber, 'Question', collect($exception->errors())->flatten()->first() ?? 'The question is invalid.');

            return;
        }
        $definitions = $validated['options'];
        $rows[] = [
            'text' => $validated['text'],
            'options' => $definitions,
            'option_texts' => array_column($definitions, 'text'),
            'correct_indexes' => collect($definitions)->keys()->filter(fn (int $index): bool => $definitions[$index]['is_correct'])->values()->all(),
            'option_marks' => collect($definitions)->mapWithKeys(fn (array $option, int $index): array => [$index => $option['marks']])->all(),
            'answer_limit' => count($correctIndexes),
            'total_marks' => array_sum(array_column($definitions, 'marks')),
        ];
    }

    private function parseNewRow(array $values, int $rowNumber, array &$rows, array &$errors, Exam $exam): void
    {
        $startErrors = count($errors);
        $question = trim((string) $values[0]);
        if ($question === '') {
            $errors[] = $this->error($rowNumber, 'Question', 'Question text is required.');
        } elseif (mb_strlen($question) > 1000) {
            $errors[] = $this->error($rowNumber, 'Question', 'Question text may not exceed 1,000 characters.');
        }

        $letters = range('A', 'F');
        $optionTexts = [];
        $indexesByLetter = [];
        foreach ($letters as $offset => $letter) {
            $text = trim((string) ($values[$offset + 1] ?? ''));
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 300) {
                $errors[] = $this->error($rowNumber, 'Option '.$letter, 'Option text may not exceed 300 characters.');
            }
            $indexesByLetter[$letter] = count($optionTexts);
            $optionTexts[] = $text;
        }
        if (count($optionTexts) < 2) {
            $errors[] = $this->error($rowNumber, 'Option A', 'A question needs at least two filled options.');
        }

        $answer = preg_replace('/\s+/u', '', (string) ($values[7] ?? '')) ?? '';
        $tokens = $answer === '' ? [] : (preg_split('/[;,]/', $answer) ?: []);
        if ($tokens === []) {
            $errors[] = $this->error($rowNumber, 'Correct Answer', 'At least one correct option is required.');
        }

        $answers = [];
        foreach ($tokens as $token) {
            if (! preg_match('/^([A-F])(?:=(.*))?$/i', $token, $match)) {
                $errors[] = $this->error($rowNumber, 'Correct Answer', 'Use option letters A to F, with optional whole-number marks such as B=3.');

                continue;
            }
            $letter = mb_strtoupper($match[1]);
            if (array_key_exists($letter, $answers)) {
                $errors[] = $this->error($rowNumber, 'Correct Answer', 'Do not repeat option '.$letter.'.');

                continue;
            }
            if (! array_key_exists($letter, $indexesByLetter)) {
                $errors[] = $this->error($rowNumber, 'Correct Answer', 'Option '.$letter.' has no text.');

                continue;
            }
            $answers[$letter] = array_key_exists(2, $match) ? $match[2] : null;
        }

        if (count($answers) > 1 && collect($answers)->contains(fn ($mark): bool => $mark === null || $mark === '')) {
            $errors[] = $this->error($rowNumber, 'Correct Answer', 'give a mark for each correct option, for example A=2, C=1.');
        }

        $correctIndexes = [];
        $optionMarks = [];
        foreach ($answers as $letter => $mark) {
            if ($mark === null || $mark === '') {
                $mark = (string) $exam->default_question_marks;
            }
            if (preg_match('/^[1-9][0-9]*$/', $mark) !== 1 || (int) $mark > 65535) {
                $errors[] = $this->error($rowNumber, 'Correct Answer', 'Marks must be whole numbers of at least 1.');

                continue;
            }
            $index = $indexesByLetter[$letter];
            $correctIndexes[] = $index;
            $optionMarks[$index] = (int) $mark;
        }
        if ($correctIndexes !== [] && count($correctIndexes) === count($optionTexts)) {
            $errors[] = $this->error($rowNumber, 'Correct Answer', 'At least one filled option must be wrong.');
        }
        if (count($errors) !== $startErrors) {
            return;
        }

        try {
            $validated = $this->workflow->validateQuestionInput($question, $optionTexts, $correctIndexes, $optionMarks, (float) $exam->default_question_marks);
        } catch (ValidationException $exception) {
            $errors[] = $this->error($rowNumber, 'Question', collect($exception->errors())->flatten()->first() ?? 'The question is invalid.');

            return;
        }
        $definitions = $validated['options'];
        $rows[] = [
            'text' => $validated['text'],
            'options' => $definitions,
            'option_texts' => array_column($definitions, 'text'),
            'correct_indexes' => collect($definitions)->keys()->filter(fn (int $index): bool => $definitions[$index]['is_correct'])->values()->all(),
            'option_marks' => collect($definitions)->mapWithKeys(fn (array $option, int $index): array => [$index => $option['marks']])->all(),
            'answer_limit' => count($correctIndexes),
            'total_marks' => array_sum(array_column($definitions, 'marks')),
        ];
    }

    private function report(array $rows, array $errors, Exam $exam): array
    {
        $existing = $exam->questions()->count();
        if ($errors === [] && $existing + count($rows) > self::MAX_ROWS) {
            $errors[] = $this->error(1, 'Question', 'The upload would take this exam above 200 questions.');
            $rows = [];
        }

        return [
            'rows' => $rows,
            'errors' => array_slice($errors, 0, 50),
            'error_count' => count($errors),
            'questions_to_add' => count($rows),
            'total_marks' => array_sum(array_column($rows, 'total_marks')),
            'resulting_question_count' => $existing + count($rows),
        ];
    }

    private function eligibleExam(User $actor, int $examId): Exam
    {
        $exam = app(CbtAccessScope::class)->managedExam($actor, $examId);
        $this->assertMutable($exam);

        return $exam;
    }

    private function assertMutable(Exam $exam): void
    {
        if ($exam->status !== 'Draft') {
            throw ValidationException::withMessages(['file' => 'Questions can be uploaded only to a Draft exam.']);
        }
        if ($exam->submissions()->exists()) {
            throw ValidationException::withMessages(['file' => 'Questions cannot be uploaded after an attempt has started.']);
        }
    }

    private function stageFor(User $actor, int $examId, string $token): array
    {
        $stage = $this->stages()[$token] ?? null;
        if (! is_array($stage)
            || (int) ($stage['owner_id'] ?? 0) !== $actor->id
            || (int) ($stage['exam_id'] ?? 0) !== $examId) {
            throw new AuthorizationException('This question upload does not belong to you or this exam.');
        }

        return $stage;
    }

    private function consumeCurrentForExam(User $actor, int $examId): void
    {
        foreach ($this->stages() as $token => $stage) {
            if (is_array($stage) && (int) ($stage['owner_id'] ?? 0) === $actor->id && (int) ($stage['exam_id'] ?? 0) === $examId) {
                $this->consume((string) $token, $stage);
            }
        }
    }

    private function cleanupExpired(User $actor): void
    {
        foreach ($this->stages() as $token => $stage) {
            if (is_array($stage) && (int) ($stage['owner_id'] ?? 0) === $actor->id && (int) ($stage['expires_at'] ?? 0) < now()->timestamp) {
                $this->consume((string) $token, $stage);
            }
        }
    }

    private function consume(string $token, array $stage): void
    {
        if (isset($stage['path'])) {
            Storage::disk('local')->delete($stage['path']);
        }
        $stages = $this->stages();
        unset($stages[$token]);
        Session::put('cbt_question_csv', $stages);
    }

    private function stages(): array
    {
        return (array) Session::get('cbt_question_csv', []);
    }

    private function normalizedHeaders(array $headers): array
    {
        return array_map(fn ($header): string => mb_strtolower(trim((string) $header)), $headers);
    }

    private function layout(array $headers): ?string
    {
        $normalized = $this->normalizedHeaders($headers);

        return match ($normalized) {
            $this->normalizedHeaders(self::HEADERS) => 'new',
            $this->normalizedHeaders(self::LEGACY_MARK_HEADERS) => 'legacy_marks',
            $this->normalizedHeaders(self::LEGACY_SCORE_HEADERS) => 'legacy_scores',
            default => null,
        };
    }

    private function mixedLayout(array $headers): bool
    {
        $headers = $this->normalizedHeaders($headers);
        $families = [
            collect($headers)->contains('correct answer'),
            collect($headers)->contains(fn (string $header): bool => str_starts_with($header, 'marks for ')),
            collect($headers)->contains(fn (string $header): bool => str_starts_with($header, 'option ') && str_ends_with($header, ' score')),
        ];

        return count(array_filter($families)) > 1;
    }

    private function error(int $row, string $column, string $message): array
    {
        return ['row' => $row, 'column' => $column, 'message' => "Row {$row}, {$column}: {$message}"];
    }
}
