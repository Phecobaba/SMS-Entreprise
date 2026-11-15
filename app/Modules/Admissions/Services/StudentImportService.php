<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Services\LearnerTerminology;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Models\StudentImport;
use App\Modules\Admissions\Models\StudentRegistrationNumberHistory;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\UsernameRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentImportService
{
    public const HEADERS = ['full_name', 'class', 'arm', 'registration_number', 'username', 'email', 'parent_name', 'parent_username', 'parent_email'];

    public function __construct(private readonly StudentAccounts $accounts) {}

    public function check(UploadedFile $file, User $actor): StudentImport
    {
        abort_unless($actor->can('create_students'), 403);
        $this->cleanupExpired();
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }
        $hash = hash('sha256', $contents);
        $this->refuseConfirmedHash($hash);
        $path = 'student-imports/'.$actor->school_id.'/'.Str::uuid().'.csv';
        Storage::disk('local')->put($path, $contents);
        $report = $this->validateContents($contents);

        return StudentImport::create([
            'owner_id' => $actor->id,
            'status' => $report['error_count'] ? 'errors' : 'checked',
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'file_hash' => $hash,
            'row_count' => $report['row_count'],
            'ready_count' => $report['ready_count'],
            'skipped_count' => $report['skipped_count'],
            'error_count' => $report['error_count'],
            'report' => $this->publicReport($report),
            'expires_at' => now()->addHours((int) config('student_imports.retention_hours', 24)),
        ]);
    }

    public function confirm(StudentImport $import, User $actor, bool $acknowledgeWarnings = false): array
    {
        abort_unless($actor->can('create_students') && $import->owner_id === $actor->id, 403);
        if ($import->status === 'completed') {
            return ['import' => $import, 'credentials' => []];
        }
        if (! $import->stored_path || ! Storage::disk('local')->exists($import->stored_path)) {
            throw ValidationException::withMessages(['file' => 'This checked file has expired. Upload it again.']);
        }
        $contents = Storage::disk('local')->get($import->stored_path);
        if (! hash_equals($import->file_hash, hash('sha256', $contents))) {
            throw ValidationException::withMessages(['file' => 'The checked file changed. Upload it again.']);
        }
        $this->refuseConfirmedHash($import->file_hash, $import->id);
        $report = $this->validateContents($contents);
        if ($report['error_count']) {
            $this->saveReport($import, $report, 'errors');
            $this->deleteStoredFile($import);
            throw ValidationException::withMessages(['file' => 'The file changed or school data changed. Check the listed errors and upload again.']);
        }
        if ($report['warnings'] && ! $acknowledgeWarnings) {
            $this->saveReport($import, $report, 'checked');
            throw ValidationException::withMessages(['create_anyway' => 'Review the possible duplicate pupils and tick Create anyway before confirming.']);
        }

        try {
            $credentials = DB::transaction(function () use ($import, $actor, $report): array {
                $locked = StudentImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'completed') {
                    return [];
                }
                $credentials = [];
                $parents = [];
                foreach ($report['planned'] as $row) {
                    $parentMode = $row['parent_mode'];
                    $parentId = $row['parent_id'];
                    if ($parentMode === 'new' && isset($parents[$row['parent_key']])) {
                        $parentMode = 'existing';
                        $parentId = $parents[$row['parent_key']];
                    }
                    $created = $this->accounts->create([
                        'name' => $row['full_name'], 'class_arm_id' => $row['class_arm_id'],
                        'registration_mode' => $row['registration_number'] ? 'typed' : 'automatic',
                        'registration_number' => $row['registration_number'], 'username' => $row['username'],
                        'email' => $row['email'], 'parent_mode' => $parentMode,
                        'parent_name' => $row['parent_name'], 'parent_username' => $row['parent_username'],
                        'parent_email' => $row['parent_email'], 'parent_id' => $parentId,
                    ], $actor);
                    if ($created['parent_user']) {
                        $parents[$row['parent_key']] = ParentProfile::where('user_id', $created['parent_user']->id)->value('id');
                    }
                    $resolvedParentId = $parentId ?: ($parents[$row['parent_key']] ?? null);
                    $credentials[] = [
                        'student_name' => $created['student_user']->name,
                        'learner_term' => app(LearnerTerminology::class)->word($created['student']),
                        'class' => $row['class_name'],
                        'arm' => $row['arm_name'],
                        'registration_number' => $created['student']->registration_number,
                        'student_username' => $created['student_user']->username,
                        'student_password' => $created['student_password'],
                        'parent_name' => $created['parent_user']?->name,
                        'parent_username' => $created['parent_user']?->username,
                        'parent_password' => $created['parent_password'],
                        'parent_profile_id' => $resolvedParentId,
                    ];
                }

                $childrenByParent = collect($credentials)
                    ->filter(fn (array $credential): bool => $credential['parent_profile_id'] !== null)
                    ->groupBy('parent_profile_id')
                    ->map(fn ($family) => $family->pluck('student_name')->values()->all());
                $schoolName = School::query()->whereKey($actor->school_id)->value('name') ?? 'School';
                $signInAddress = route('login');
                $credentials = array_map(function (array $credential) use ($childrenByParent, $schoolName, $signInAddress): array {
                    $credential['school_name'] = $schoolName;
                    $credential['sign_in_address'] = $signInAddress;
                    $credential['parent_children'] = $credential['parent_password']
                        ? $childrenByParent->get($credential['parent_profile_id'], [])
                        : [];
                    unset($credential['parent_profile_id']);

                    return $credential;
                }, $credentials);
                $locked->update([
                    'status' => 'completed', 'confirmed_at' => now(),
                    'row_count' => $report['row_count'], 'ready_count' => $report['ready_count'],
                    'skipped_count' => $report['skipped_count'], 'error_count' => 0,
                    'report' => $this->publicReport($report),
                ]);

                return $credentials;
            });
        } catch (Throwable $exception) {
            $import->refresh()->update(['status' => 'failed', 'report' => ['message' => 'Nothing was created. Correct the problem and upload the file again.']]);
            $this->deleteStoredFile($import);
            throw $exception;
        }
        $this->deleteStoredFile($import);

        return ['import' => $import->refresh(), 'credentials' => $credentials];
    }

    /** @return array<int, array{class:string,arm:string,path:string}> */
    public function classOptions(): array
    {
        $classes = SchoolClass::with(['level.section', 'arms'])->whereHas('level.section')->orderBy('id')->get();
        $duplicateNames = $classes->groupBy(fn (SchoolClass $class): string => mb_strtolower(trim($class->name)))
            ->filter(fn ($matches): bool => $matches->count() > 1)
            ->keys();

        return $classes->flatMap(function (SchoolClass $class) use ($duplicateNames): array {
            $path = $this->classPath($class);
            $label = $duplicateNames->contains(mb_strtolower(trim($class->name))) ? $path : $class->name;

            return $class->arms->map(fn (ClassArm $arm): array => [
                'class' => $label,
                'arm' => $arm->name,
                'path' => $path,
            ])->all();
        })->values()->all();
    }

    public function effectiveRowCap(?int $maxExecutionTime = null): int
    {
        $configured = max(0, (int) config('student_imports.row_cap', 50));
        $limit = $maxExecutionTime ?? (int) ini_get('max_execution_time');
        if ($limit === 0) {
            return $configured;
        }
        $secondsPerRow = (float) config('student_imports.seconds_per_row', 0.5);
        if ($secondsPerRow <= 0) {
            $secondsPerRow = 0.5;
        }

        return min($configured, (int) floor((0.5 * $limit) / $secondsPerRow));
    }

    public function cleanupExpired(): int
    {
        $cleaned = 0;
        StudentImport::whereNotNull('stored_path')->where('expires_at', '<=', now())->get()->each(function (StudentImport $import) use (&$cleaned): void {
            $this->deleteStoredFile($import);
            $import->update(['status' => $import->status === 'completed' ? 'completed' : 'expired']);
            $cleaned++;
        });

        return $cleaned;
    }

    private function validateContents(string $contents): array
    {
        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            return $this->fatal('file', 'Use a UTF-8 CSV file.');
        }
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $headers = fgetcsv($stream);
        if ($headers !== self::HEADERS) {
            fclose($stream);

            return $this->fatal('header', 'Use the template unchanged. The nine column headings must match exactly.');
        }
        $rawRows = [];
        $line = 1;
        while (($values = fgetcsv($stream)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), '');
            $rawRows[] = ['row' => $line] + array_map(fn ($value) => trim((string) $value), array_combine(self::HEADERS, $values));
        }
        fclose($stream);
        $cap = $this->effectiveRowCap();
        if (count($rawRows) > $cap) {
            return $this->fatal('file', 'This file has '.count($rawRows)." students. The current limit is {$cap}. Split it into smaller files.", count($rawRows));
        }

        $classes = SchoolClass::with(['level.section', 'arms'])->whereHas('level.section')->get();
        $arms = $classes->flatMap->arms;
        $classesByName = $classes->groupBy(fn (SchoolClass $class): string => mb_strtolower(trim($class->name)));
        $classesByPath = $classes->groupBy(fn (SchoolClass $class): string => mb_strtolower($this->classPath($class)));
        $errors = [];
        $planned = [];
        $skipped = [];
        $warnings = [];
        $reservedUsers = [];
        $reservedEmails = [];
        $reservedNumbers = [];
        $parentPlans = [];
        $existingByNameAndArm = Student::with('user')->whereIn('class_arm_id', $arms->pluck('id'))
            ->get()->groupBy(fn (Student $student): string => $student->class_arm_id.'|'.$this->normalizeName($student->user->name));
        foreach ($rawRows as $raw) {
            $rowErrors = [];
            if ($raw['full_name'] === '') {
                $rowErrors[] = $this->error($raw['row'], 'full_name', 'Enter the student full name.');
            }
            $classKey = mb_strtolower(trim($raw['class']));
            $pathMatches = $classesByPath->get($classKey, collect());
            $nameMatches = $classesByName->get($classKey, collect());
            $class = $pathMatches->count() === 1 ? $pathMatches->first() : ($nameMatches->count() === 1 ? $nameMatches->first() : null);
            if (! $class) {
                if ($nameMatches->count() > 1) {
                    $paths = $nameMatches->map(fn (SchoolClass $match): string => $this->classPath($match))->join('; ');
                    $rowErrors[] = $this->error($raw['row'], 'class', "That class name is used in more than one place: {$paths}. Enter one of these full paths in class.");
                } else {
                    $rowErrors[] = $this->error($raw['row'], 'class', 'That class does not exist in this school.');
                }
            }
            $arm = null;
            if ($class) {
                $classArms = $class->arms;
                if ($raw['arm'] === '' && $classArms->count() === 1) {
                    $arm = $classArms->first();
                } elseif ($raw['arm'] === '') {
                    $rowErrors[] = $this->error($raw['row'], 'arm', 'Enter an arm. This class has: '.$classArms->pluck('name')->join(', ').'.');
                } else {
                    $armMatches = $classArms->filter(fn (ClassArm $candidate): bool => mb_strtolower(trim($candidate->name)) === mb_strtolower($raw['arm']));
                    if ($armMatches->count() === 1) {
                        $arm = $armMatches->first();
                    } else {
                        $rowErrors[] = $this->error($raw['row'], 'arm', 'That arm does not exist in this class.');
                    }
                }
            }
            $number = strtoupper($raw['registration_number']);
            if ($number !== '' && (strlen($number) > 64 || preg_match('/^[A-Z0-9.\/-]+$/', $number) !== 1)) {
                $rowErrors[] = $this->error($raw['row'], 'registration_number', 'Use letters, numbers, hyphens, slashes or dots only.');
            }
            $sameNameAndClass = $arm ? $existingByNameAndArm->get($arm->id.'|'.$this->normalizeName($raw['full_name']), collect()) : collect();
            $duplicate = $number !== '' ? $sameNameAndClass->first(fn (Student $student): bool => strtoupper((string) $student->registration_number) === $number) : null;
            if ($duplicate) {
                $skipped[] = ['row' => $raw['row'], 'message' => 'Exact duplicate already exists.'];

                continue;
            }
            foreach ($sameNameAndClass as $possibleDuplicate) {
                $warnings[] = [
                    'row' => $raw['row'],
                    'matching_name' => $possibleDuplicate->user->name,
                    'class' => $this->classPath($class).' / '.$arm->name,
                    'registration_number' => $possibleDuplicate->registration_number,
                ];
            }
            if ($number !== '' && (Student::where('registration_number', $number)->exists() || StudentRegistrationNumberHistory::where('new_number', $number)->exists())) {
                $rowErrors[] = $this->error($raw['row'], 'registration_number', 'That registration number has already been issued.');
            }
            $username = $raw['username'] !== '' ? strtolower($raw['username']) : $this->suggestUsername($raw['full_name'], $reservedUsers);
            $this->validateUsername($username, $raw['row'], 'username', $reservedUsers, $rowErrors);
            $email = $this->normalizeEmail($raw['email'], $raw['row'], 'email', $reservedEmails, $rowErrors);
            $parent = $this->resolveParent($raw, $reservedUsers, $reservedEmails, $parentPlans, $rowErrors);
            if ($number !== '') {
                if (isset($reservedNumbers[$number])) {
                    $rowErrors[] = $this->error($raw['row'], 'registration_number', 'Also used on row '.$reservedNumbers[$number].'.');
                    $errors[] = $this->error($reservedNumbers[$number], 'registration_number', 'Also used on row '.$raw['row'].'.');
                } else {
                    $reservedNumbers[$number] = $raw['row'];
                }
            }
            if ($rowErrors) {
                array_push($errors, ...$rowErrors);

                continue;
            }
            $planned[] = [
                'row' => $raw['row'], 'full_name' => $raw['full_name'], 'class_arm_id' => $arm->id,
                'class' => $class->name, 'arm' => $arm->name,
                'class_name' => $class->name, 'arm_name' => $arm->name,
                'registration_number' => $number ?: null,
                'username' => $username, 'email' => $email, 'parent_name' => $parent['name'],
                'parent_username' => $parent['username'], 'parent_email' => $parent['email'],
                'parent_mode' => $parent['mode'], 'parent_id' => $parent['id'], 'parent_key' => $parent['key'],
            ];
        }

        return [
            'row_count' => count($rawRows), 'ready_count' => count($planned), 'skipped_count' => count($skipped),
            'error_count' => collect($errors)->pluck('row')->unique()->count(), 'errors' => $errors, 'skipped' => $skipped,
            'warnings' => $warnings,
            'preview' => array_map(fn ($row) => array_intersect_key($row, array_flip(['row', 'full_name', 'class', 'arm', 'registration_number', 'username', 'email', 'parent_name', 'parent_username', 'parent_email'])), $planned),
            'planned' => $planned,
        ];
    }

    private function resolveParent(array $raw, array &$reservedUsers, array &$reservedEmails, array &$plans, array &$errors): array
    {
        if ($raw['parent_name'] === '' && $raw['parent_username'] === '' && $raw['parent_email'] === '') {
            return ['mode' => 'none', 'id' => null, 'key' => '', 'name' => null, 'username' => null, 'email' => null];
        }
        $username = strtolower($raw['parent_username']);
        $email = strtolower($raw['parent_email']);
        $existing = null;
        if ($username !== '') {
            $existing = ParentProfile::whereHas('user', fn ($query) => $query->whereRaw('LOWER(username) = ?', [$username]))->with('user')->first();
        } elseif ($email !== '') {
            $existing = ParentProfile::whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))->with('user')->first();
        }
        if ($existing) {
            if (($username && $existing->user->username !== $username) || ($email && strtolower((string) $existing->user->email) !== $email)) {
                $errors[] = $this->error($raw['row'], 'parent_username', 'The parent username and email belong to different details.');
            }

            return ['mode' => 'existing', 'id' => $existing->id, 'key' => 'existing:'.$existing->id, 'name' => $existing->user->name, 'username' => $existing->user->username, 'email' => $existing->user->email];
        }
        if ($raw['parent_name'] === '') {
            $errors[] = $this->error($raw['row'], 'parent_name', 'Enter the parent name when creating a new parent.');
        }
        $username = $username ?: $this->suggestUsername($raw['parent_name'] ?: 'parent', $reservedUsers);
        $key = $raw['parent_username'] !== '' ? 'username:'.$username : ($email !== '' ? 'email:'.$email : 'row:'.$raw['row']);
        if (isset($plans[$key])) {
            $plan = $plans[$key];
            if (mb_strtolower($plan['name']) !== mb_strtolower($raw['parent_name']) || strtolower((string) $plan['email']) !== $email) {
                $errors[] = $this->error($raw['row'], 'parent_username', 'Rows sharing a parent must use the same parent name, username and email.');
            }

            return $plan;
        }
        $this->validateUsername($username, $raw['row'], 'parent_username', $reservedUsers, $errors);
        $normalizedEmail = $this->normalizeEmail($email, $raw['row'], 'parent_email', $reservedEmails, $errors);
        $plan = ['mode' => 'new', 'id' => null, 'key' => $key, 'name' => $raw['parent_name'], 'username' => $username, 'email' => $normalizedEmail];
        $plans[$key] = $plan;

        return $plan;
    }

    private function validateUsername(string $username, int $row, string $column, array &$reserved, array &$errors): void
    {
        $rules = app(UsernameRules::class);
        if ($message = $rules->formatError($username)) {
            $errors[] = $this->error($row, $column, $message);
        } elseif ($rules->isTaken($username)) {
            $errors[] = $this->error($row, $column, 'That username is already in use.');
        } elseif (isset($reserved[$username])) {
            $errors[] = $this->error($row, $column, 'Also used on row '.$reserved[$username].'.');
            $errors[] = $this->error($reserved[$username], $column, 'Also used on row '.$row.'.');
        } else {
            $reserved[$username] = $row;
        }
    }

    private function normalizeEmail(string $email, int $row, string $column, array &$reserved, array &$errors): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->error($row, $column, 'Enter a valid email address.');
        } elseif (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $errors[] = $this->error($row, $column, 'That email is already in use.');
        } elseif (isset($reserved[$email])) {
            $errors[] = $this->error($row, $column, 'Also used on row '.$reserved[$email].'.');
            $errors[] = $this->error($reserved[$email], $column, 'Also used on row '.$row.'.');
        } else {
            $reserved[$email] = $row;
        }

        return $email;
    }

    private function suggestUsername(string $name, array $reserved): string
    {
        $base = trim(str_replace('-', '.', Str::slug($name)), '.');
        $base = substr($base ?: 'student', 0, 30);
        for ($suffix = 0; $suffix < 10000; $suffix++) {
            $candidate = $suffix ? substr($base, 0, 30 - strlen((string) $suffix)).$suffix : $base;
            if (! isset($reserved[$candidate]) && ! app(UsernameRules::class)->isTaken($candidate)) {
                return $candidate;
            }
        }
        throw ValidationException::withMessages(['username' => 'A username could not be suggested.']);
    }

    private function classPath(SchoolClass $class): string
    {
        return implode(' / ', [$class->level->section->name, $class->level->name, $class->name]);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }

    private function refuseConfirmedHash(string $hash, ?int $exceptId = null): void
    {
        $query = StudentImport::where('status', 'completed')->where('file_hash', $hash);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        $previous = $query->orderBy('confirmed_at')->first();
        if ($previous) {
            $date = $previous->confirmed_at?->timezone('Africa/Lagos')->format('j M Y') ?? $previous->updated_at->format('j M Y');
            throw ValidationException::withMessages(['file' => "This file was already imported on {$date}."]);
        }
    }

    private function publicReport(array $report): array
    {
        return array_intersect_key($report, array_flip(['row_count', 'ready_count', 'skipped_count', 'error_count', 'errors', 'skipped', 'warnings', 'preview']));
    }

    private function fatal(string $column, string $message, int $rows = 0): array
    {
        return ['row_count' => $rows, 'ready_count' => 0, 'skipped_count' => 0, 'error_count' => 1, 'errors' => [$this->error(0, $column, $message)], 'skipped' => [], 'warnings' => [], 'preview' => [], 'planned' => []];
    }

    private function error(int $row, string $column, string $message): array
    {
        return compact('row', 'column', 'message');
    }

    private function saveReport(StudentImport $import, array $report, string $status): void
    {
        $import->update(['status' => $status, 'row_count' => $report['row_count'], 'ready_count' => $report['ready_count'], 'skipped_count' => $report['skipped_count'], 'error_count' => $report['error_count'], 'report' => $this->publicReport($report)]);
    }

    private function deleteStoredFile(StudentImport $import): void
    {
        if ($import->stored_path) {
            Storage::disk('local')->delete($import->stored_path);
            $import->update(['stored_path' => null]);
        }
    }
}
