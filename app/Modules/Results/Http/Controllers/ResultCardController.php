<?php

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Core\Services\CurrentSchoolProfile;
use App\Modules\Payments\Services\ResultPaymentAccess;
use App\Modules\Portals\Services\PortalContext;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Services\ResultCards;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResultCardController extends Controller
{
    public function myResult(int $card, ResultCards $cards, ResultPaymentAccess $paymentAccess, Request $request): View
    {
        $record = ResultCard::findOrFail($card);
        $context = app(PortalContext::class)->for($request);
        abort_unless($context && $context['student_id'] === $record->student_id, 403);
        $paymentAccess->authorizeOwnership($record->student_id, $request->user());
        try {
            $paymentAccess->authorize($record, $request->user());
        } catch (AuthorizationException) {
            return view('portals.card', ['context' => $context, 'locked' => true,
                'invoice' => $paymentAccess->unpaidInvoice($record)]);
        }
        $broadsheet = $cards->broadsheet($record->class_arm_id, $record->term_id);

        return view('portals.card', [
            ...$cards->detail($record), 'context' => $context, 'locked' => false,
            'position' => collect($broadsheet)->firstWhere('student_id', $record->student_id)['position'] ?? null,
            'classSize' => count($broadsheet),
            'average' => collect($broadsheet)->firstWhere('student_id', $record->student_id)['average'] ?? null,
            'class' => app(ClassArmDirectory::class)->label($record->class_arm_id),
        ]);
    }

    public function myResultPdf(int $card, ResultCards $cards, ResultPaymentAccess $paymentAccess, Request $request)
    {
        $record = ResultCard::findOrFail($card);
        $context = app(PortalContext::class)->for($request);
        abort_unless($context && $context['student_id'] === $record->student_id, 403);
        $paymentAccess->authorize($record, $request->user());
        $data = [
            ...$cards->detail($record),
            'profile' => app(CurrentSchoolProfile::class)->printProfile(),
            'broadsheet' => $cards->broadsheet($record->class_arm_id, $record->term_id),
        ];
        $pdf = Pdf::loadView('results.card-pdf', $data)->setPaper('a4', 'portrait');
        $pdf->render();
        abort_if($pdf->getDomPDF()->getCanvas()->get_page_count() !== 1, 422, 'This report card exceeds one A4 page.');

        return $pdf->stream('result-card-'.$record->id.'.pdf');
    }

    public function show(int $card, ResultCards $cards, Request $request): View
    {
        $record = ResultCard::findOrFail($card);
        $cards->authorizeView($record, $request->user());

        return view('results.card', [
            ...$cards->detail($record),
            'profile' => app(CurrentSchoolProfile::class)->printProfile(),
            'broadsheet' => $cards->broadsheet($record->class_arm_id, $record->term_id),
        ]);
    }

    public function pdf(int $card, ResultCards $cards, Request $request)
    {
        $record = ResultCard::findOrFail($card);
        $cards->authorizeView($record, $request->user());
        $data = [
            ...$cards->detail($record),
            'profile' => app(CurrentSchoolProfile::class)->printProfile(),
            'broadsheet' => $cards->broadsheet($record->class_arm_id, $record->term_id),
        ];
        $pdf = Pdf::loadView('results.card-pdf', $data)->setPaper('a4', 'portrait');
        $pdf->render();
        abort_if($pdf->getDomPDF()->getCanvas()->get_page_count() !== 1, 422, 'This report card exceeds one A4 page. Shorten its content before printing.');

        return $pdf->stream('result-card-'.$record->id.'.pdf');
    }

    public function defaultComment(Request $request, ResultCards $cards): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'comment_type' => ['required', 'in:Principal,Proprietor'],
            'comment_text' => ['required', 'string', 'max:320'],
        ]);
        $cards->setDefault((int) $data['term_id'], isset($data['section_id']) ? (int) $data['section_id'] : null, $data['comment_type'], trim($data['comment_text']), $request->user());

        return back()->with('status', 'Term default comment saved.');
    }

    public function comments(int $card, Request $request, ResultCards $cards): RedirectResponse
    {
        $record = ResultCard::findOrFail($card);
        $cards->authorizeView($record, $request->user());
        $data = $request->validate([
            'class_teacher_comment' => ['nullable', 'string', 'max:320'],
            'principal_comment' => ['nullable', 'string', 'max:320'],
            'proprietor_comment' => ['nullable', 'string', 'max:320'],
            'next_term_begins' => ['nullable', 'date'],
        ]);
        DB::transaction(function () use ($request, $cards, $record, $data): void {
            if ($request->exists('class_teacher_comment')) {
                $cards->teacherComment($record, $data['class_teacher_comment'] ?? null, $request->user());
            }
            if ($request->exists('principal_comment')) {
                $cards->personalize($record, 'Principal', $data['principal_comment'] ?? null, $request->user());
            }
            if ($request->exists('proprietor_comment')) {
                $cards->personalize($record, 'Proprietor', $data['proprietor_comment'] ?? null, $request->user());
            }
            if ($request->exists('next_term_begins')) {
                Gate::authorize('manage_result_settings');
                $record->update(['next_term_begins' => $data['next_term_begins'] ?? null]);
            }
        });

        return back()->with('status', 'Card comments saved.');
    }
}
