<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Models\StudentImport;
use App\Modules\Admissions\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    public function create(StudentImportService $imports): View
    {
        Gate::authorize('create_students');
        $imports->cleanupExpired();

        return view('students.import', ['import' => null, 'classOptions' => $imports->classOptions(),
            'rowCap' => $imports->effectiveRowCap(), 'maxKilobytes' => (int) config('student_imports.max_kilobytes', 1024)]);
    }

    public function template(StudentImportService $imports): Response
    {
        Gate::authorize('create_students');
        $example = $imports->classOptions()[0] ?? null;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, StudentImportService::HEADERS, ',', '"', '');
        if ($example !== null) {
            fputcsv($stream, ['Example Student', $example['class'], $example['arm'], '', 'example.student', 'student@example.test', 'Example Parent', 'example.parent', 'parent@example.test'], ',', '"', '');
        }
        rewind($stream);
        $csv = str_replace("\n", "\r\n", stream_get_contents($stream));
        fclose($stream);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="oakfield-student-import.csv"', 'Cache-Control' => 'no-store']);
    }

    public function check(Request $request, StudentImportService $imports): RedirectResponse
    {
        Gate::authorize('create_students');
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(int) config('student_imports.max_kilobytes', 1024)]]);
        $import = $imports->check($data['file'], $request->user());

        return redirect()->route('students.imports.show', $import);
    }

    public function show(StudentImport $import, Request $request, StudentImportService $imports): View
    {
        Gate::authorize('create_students');
        abort_unless($import->owner_id === $request->user()->id, 403);
        $imports->cleanupExpired();

        return view('students.import', ['import' => $import->refresh(), 'classOptions' => $imports->classOptions(),
            'rowCap' => $imports->effectiveRowCap(), 'maxKilobytes' => (int) config('student_imports.max_kilobytes', 1024)]);
    }

    public function confirm(StudentImport $import, Request $request, StudentImportService $imports): Response
    {
        Gate::authorize('create_students');
        abort_unless($import->owner_id === $request->user()->id, 403);
        if ($import->error_count > 0 || ! in_array($import->status, ['checked', 'completed'], true)) {
            throw ValidationException::withMessages(['file' => 'Fix every error and check the corrected file before confirming.']);
        }
        $result = $imports->confirm($import, $request->user(), $request->boolean('create_anyway'));

        return response()->view('students.import-result', $result)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')->header('Pragma', 'no-cache');
    }
}
