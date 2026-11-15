<?php

namespace App\Modules\ENotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\ENotes\Services\ENoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ENoteController extends Controller
{
    public function index(Request $request, ENoteService $service, ResultTeachingAssignments $assignments): View
    {
        $filters = $request->validate(['pair' => ['nullable', 'regex:/^\d+:\d+$/']]);
        [$classId, $subjectId] = $this->pairIds($filters['pair'] ?? null);
        $pairs = $assignments->pairOptionsFor($request->user(), 'manage_enotes');

        return view('enotes.index', [
            'notes' => $service->listing($request->user(), $classId, $subjectId),
            'pairs' => $pairs,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, ENoteService $service): RedirectResponse
    {
        $data = $request->validate([
            'pair' => ['required', 'regex:/^\d+:\d+$/'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240'],
            'link' => ['nullable', 'url:https', 'max:2048'],
        ]);
        [$data['class_id'], $data['subject_id']] = $this->pairIds($data['pair']);
        $service->upload($data, $request->user());

        return redirect()->route('enotes.index')->with('status', 'E-Note uploaded.');
    }

    public function download(int $note, Request $request, ENoteService $service): StreamedResponse
    {
        $item = $service->accessible($note, $request->user());
        abort_unless($item->file_path, 404);

        return Storage::disk('local')->download($item->file_path);
    }

    private function pairIds(?string $pair): array
    {
        return $pair ? array_map('intval', explode(':', $pair, 2)) : [null, null];
    }
}
