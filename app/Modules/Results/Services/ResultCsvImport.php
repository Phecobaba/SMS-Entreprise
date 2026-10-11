<?php

namespace App\Modules\Results\Services;

use App\Modules\Core\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultCsvImport
{
    public function import(UploadedFile $file, User $actor): int
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            if (array_map('trim', $header) !== ['student_id', 'subject_id', 'term_id', 'component_id', 'score']) {
                throw ValidationException::withMessages(['file' => 'CSV columns must be student_id, subject_id, term_id, component_id, score in that order.']);
            }

            $groups = [];
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($line > 501) {
                    throw ValidationException::withMessages(['file' => 'Upload at most 500 score rows at a time.']);
                }
                if (count($row) !== 5 || filter_var($row[0], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || filter_var($row[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || filter_var($row[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || filter_var($row[3], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || ! is_numeric($row[4])) {
                    throw ValidationException::withMessages(['file' => "Invalid score data on CSV line {$line}."]);
                }
                $studentId = (int) $row[0];
                $subjectId = (int) $row[1];
                $termId = (int) $row[2];
                $componentId = (int) $row[3];
                $key = "{$studentId}:{$subjectId}:{$termId}";
                if (isset($groups[$key]['scores'][$componentId])) {
                    throw ValidationException::withMessages(['file' => "Duplicate component on CSV line {$line}."]);
                }
                $groups[$key]['student_id'] = $studentId;
                $groups[$key]['subject_id'] = $subjectId;
                $groups[$key]['term_id'] = $termId;
                $groups[$key]['scores'][$componentId] = $row[4];
            }
        } finally {
            fclose($handle);
        }

        if ($groups === []) {
            throw ValidationException::withMessages(['file' => 'The CSV file has no score rows.']);
        }

        DB::transaction(function () use ($groups, $actor): void {
            foreach ($groups as $group) {
                app(ResultWorkflow::class)->saveDraft($group['student_id'], $group['subject_id'], $group['term_id'], $group['scores'], $actor);
            }
        });

        return count($groups);
    }
}
