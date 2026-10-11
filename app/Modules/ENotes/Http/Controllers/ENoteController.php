<?php

namespace App\Modules\ENotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\ENotes\Services\ENoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ENoteController extends Controller
{
    public function index(Request $request, ENoteService $service, ResultAcademicCatalog $catalog): View
    {
        $filters = $request->validate(['class_id' => ['nullable', 'integer'], 'subject_id' => ['nullable', 'integer']]);
        $options = $catalog->options();

        return view('enotes.index', [
            'notes' => $service->listing($request->user(), $filters['class_id'] ?? null, $filters['subject_id'] ?? null),
            'classes' => $options['classes'], 'subjects' => $options['subjects'],
            'classLabels' => $options['classes']->pluck('name', 'id'),
            'subjectLabels' => $options['subjects']->pluck('name', 'id'),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, ENoteService $service): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer'], 'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240'],
            'link' => ['nullable', 'url:https', 'max:2048'],
        ]);
        $service->upload($data, $request->user());

        return back()->with('status', 'E-Note uploaded.');
    }

    public function download(int $note, Request $request, ENoteService $service): StreamedResponse
    {
        $item = $service->accessible($note, $request->user());
        abort_unless($item->file_path, 404);

        return Storage::disk('local')->download($item->file_path);
    }
}
