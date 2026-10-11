<?php

namespace App\Modules\ENotes\Services;

use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Core\Models\User;
use App\Modules\ENotes\Models\ENote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ENoteService
{
    public function upload(array $data, User $actor): ENote
    {
        Gate::forUser($actor)->authorize('upload_enotes');
        app(ResultAcademicCatalog::class)->assertClassSubject((int) $data['class_id'], (int) $data['subject_id']);
        $this->assertScope($actor, (int) $data['class_id'], (int) $data['subject_id']);
        $file = $data['file'] ?? null;
        $link = $data['link'] ?? null;
        if (($file instanceof UploadedFile) === (bool) $link) {
            throw ValidationException::withMessages(['file' => 'Provide either one file or one HTTPS link.']);
        }
        if ($link && ! str_starts_with(strtolower($link), 'https://')) {
            throw ValidationException::withMessages(['link' => 'The link must use HTTPS.']);
        }

        $path = $file instanceof UploadedFile ? $file->store('enotes', 'local') : null;
        try {
            return ENote::create([
                'class_id' => $data['class_id'], 'subject_id' => $data['subject_id'],
                'title' => $data['title'], 'file_path' => $path, 'link' => $link,
                'uploaded_by' => $actor->id,
            ]);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function listing(User $actor, ?int $classId, ?int $subjectId)
    {
        Gate::forUser($actor)->authorize('upload_enotes');
        $query = ENote::orderByDesc('id');
        if ($classId) {
            $query->where('class_id', $classId);
        }
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }
        if (! Gate::forUser($actor)->allows('manage_enotes')) {
            $scopes = app(ResultTeachingAssignments::class)->pairsFor($actor);
            $query->where(function ($builder) use ($scopes): void {
                foreach ($scopes as $pair) {
                    $builder->orWhere(fn ($q) => $q->where('class_id', $pair['class_id'])->where('subject_id', $pair['subject_id']));
                }
                if ($scopes === []) {
                    $builder->whereRaw('1 = 0');
                }
            });
        }

        return $query->paginate(20)->withQueryString();
    }

    public function accessible(int $id, User $actor): ENote
    {
        Gate::forUser($actor)->authorize('upload_enotes');
        $note = ENote::findOrFail($id);
        $this->assertScope($actor, $note->class_id, $note->subject_id);

        return $note;
    }

    public function studentListing(User $actor, ?int $subjectId): array
    {
        $student = app(StudentDirectory::class)->forUser($actor);
        abort_unless($student, 403);
        $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];
        $base = ENote::where('class_id', $classId);
        $subjectIds = (clone $base)->distinct()->pluck('subject_id')->all();
        $query = $base->with('subject')->latest('id');
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return ['notes' => $query->paginate(20)->withQueryString(),
            'subjects' => app(ResultAcademicCatalog::class)->labels($subjectIds, [])['subjects']];
    }

    public function studentAccessible(int $id, User $actor): ENote
    {
        $student = app(StudentDirectory::class)->forUser($actor);
        abort_unless($student, 403);
        $classId = app(ResultAcademicCatalog::class)->placement($student->class_arm_id)['class_id'];

        return ENote::whereKey($id)->where('class_id', $classId)->firstOrFail();
    }

    private function assertScope(User $actor, int $classId, int $subjectId): void
    {
        if (! Gate::forUser($actor)->allows('manage_enotes')
            && ! app(ResultTeachingAssignments::class)->mayEnter($actor, $classId, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => 'You are not assigned to teach this class and subject.']);
        }
    }
}
