<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Models\FeeItem;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Services\FeeInvoicingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeeInvoicingController extends Controller
{
    public function schedule(Request $request): View
    {
        $termId = (int) ($request->integer('term') ?: AcademicTerm::latest('start_date')->value('id'));
        if ($termId) {
            AcademicTerm::findOrFail($termId);
        }

        return view('payments.fee-schedule', [
            'terms' => AcademicTerm::with('session')->latest('start_date')->get(), 'termId' => $termId,
            'items' => FeeItem::withCount(['scheduleLines', 'invoiceLines'])->orderBy('name')->get(),
            'lines' => FeeScheduleLine::with(['feeItem', 'level', 'schoolClass', 'department'])->where('term_id', $termId)->get(),
            'levels' => AcademicLevel::orderBy('name')->limit(100)->get(),
            'classes' => SchoolClass::with('level')->orderBy('position')->limit(100)->get(),
            'departments' => Department::orderBy('name')->limit(100)->get(),
            'copyPlan' => null,
        ]);
    }

    public function previewScheduleCopy(Request $request, FeeInvoicingService $service): View
    {
        $data = $this->copyData($request);
        $view = $this->schedule($request->merge(['term' => $data['target_term_id']]))->getData();
        $view['copyPlan'] = $service->previewScheduleCopy($data['source_term_id'], $data['target_term_id'], $data['scope_mode'], $data['scope_type'] ?? null, $data['scope_id'] ?? null);

        return view('payments.fee-schedule', $view);
    }

    public function applyScheduleCopy(Request $request, FeeInvoicingService $service): RedirectResponse
    {
        $data = $this->copyData($request);
        $report = $service->applyScheduleCopy($data['source_term_id'], $data['target_term_id'], $data['scope_mode'], $data['scope_type'] ?? null, $data['scope_id'] ?? null, $request->user());

        return redirect()->route('fees.schedule.index', ['term' => $data['target_term_id']])
            ->with('status', $report['copied'].' schedule lines copied; '.$report['existing_count'].' existing and '.$report['missing_scope_count'].' missing-scope lines skipped.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('fee_items')->where('school_id', app(TenantContext::class)->id())],
        ]);
        FeeItem::create($data);

        return back()->with('status', 'Fee item added.');
    }

    public function destroyItem(FeeItem $item): RedirectResponse
    {
        if ($item->scheduleLines()->exists() || $item->invoiceLines()->exists()) {
            throw ValidationException::withMessages(['item' => 'This fee item is already in use and cannot be removed.']);
        }
        $item->delete();

        return back()->with('status', 'Fee item removed.');
    }

    public function storeLine(Request $request): RedirectResponse
    {
        $data = $this->lineData($request);
        $duplicate = FeeScheduleLine::where('term_id', $data['term_id'])->where('fee_item_id', $data['fee_item_id'])
            ->where('academic_level_id', $data['academic_level_id'] ?? null)->where('class_id', $data['class_id'] ?? null)
            ->where('department_id', $data['department_id'] ?? null)->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['fee_item_id' => 'That fee item is already on this scope for the term.']);
        }
        FeeScheduleLine::create($data);

        return back()->with('status', 'Schedule line added. Changing it later will not change invoices already issued.');
    }

    public function updateLine(FeeScheduleLine $line, Request $request): RedirectResponse
    {
        $data = $request->validate(['amount_naira' => ['required', 'regex:/^(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]{1,2})?$/'], 'is_required' => ['nullable', 'boolean']]);
        $line->update(['amount_kobo' => $this->kobo($data['amount_naira']), 'is_required' => $request->boolean('is_required')]);

        return back()->with('status', 'Schedule price updated. Existing invoices were not changed.');
    }

    public function destroyLine(FeeScheduleLine $line): RedirectResponse
    {
        $line->delete();

        return back()->with('status', 'Schedule line removed. Existing invoices were not changed.');
    }

    public function generate(): View
    {
        return view('payments.invoices-generate', $this->catalog());
    }

    public function preview(Request $request, FeeInvoicingService $service): View
    {
        $data = $this->generationData($request);

        return view('payments.invoices-generate', [...$this->catalog(), 'plan' => $service->preview($data['term_id'], $data['scope_type'], $data['scope_id'] ?? null), 'selection' => $data]);
    }

    public function apply(Request $request, FeeInvoicingService $service): RedirectResponse
    {
        $data = $this->generationData($request);
        $report = $service->apply($data['term_id'], $data['scope_type'], $data['scope_id'] ?? null, $data['due_date'], $request->user());

        return redirect()->route('fees.generate.index')->with('status', "{$report['created']} invoices created; {$report['skipped']} skipped.");
    }

    public function notInvoiced(Request $request, FeeInvoicingService $service): View
    {
        $termId = (int) ($request->integer('term') ?: AcademicTerm::latest('start_date')->value('id'));
        if ($termId) {
            AcademicTerm::findOrFail($termId);
        }
        $students = Student::active()->with(['user', 'classArm.schoolClass'])->whereNotIn('id', Invoice::where('kind', Invoice::KIND_TERM_FEE)
            ->where('term_id', $termId)->select('student_id'))->orderBy('id')->paginate(20)->withQueryString();
        $schedules = $students->getCollection()->mapWithKeys(fn (Student $student) => [$student->id => $service->linesForStudent($student, $termId)]);

        return view('payments.invoices-not-invoiced', [
            ...$this->catalog(), 'termId' => $termId, 'students' => $students, 'schedules' => $schedules,
        ]);
    }

    public function invoiceStudent(Student $student, Request $request, FeeInvoicingService $service): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')], 'due_date' => ['required', 'date'],
            'extras' => ['nullable', 'array'], 'extras.*' => ['integer'], 'extras_separate' => ['nullable', 'boolean'],
        ]);
        AcademicTerm::findOrFail((int) $data['term_id']);
        $service->invoiceStudent($student, (int) $data['term_id'], $data['due_date'], $data['extras'] ?? [], $request->boolean('extras_separate'), $request->user());

        return back()->with('status', 'Student invoice created.');
    }

    private function lineData(Request $request): array
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')],
            'scope_type' => ['required', Rule::in(['level', 'class', 'department'])],
            'scope_id' => ['required', 'integer'], 'fee_item_id' => ['required', 'integer', Rule::exists('fee_items', 'id')],
            'amount_naira' => ['required', 'regex:/^(?:[0-9]{1,3}(?:,[0-9]{3})+|[0-9]+)(?:\.[0-9]{1,2})?$/'], 'is_required' => ['nullable', 'boolean'],
        ]);
        $columns = ['level' => 'academic_level_id', 'class' => 'class_id', 'department' => 'department_id'];
        $models = ['level' => AcademicLevel::class, 'class' => SchoolClass::class, 'department' => Department::class];
        AcademicTerm::findOrFail((int) $data['term_id']);
        FeeItem::findOrFail((int) $data['fee_item_id']);
        $models[$data['scope_type']]::findOrFail((int) $data['scope_id']);

        return [
            'term_id' => (int) $data['term_id'], 'scope_type' => $data['scope_type'], $columns[$data['scope_type']] => (int) $data['scope_id'],
            'fee_item_id' => (int) $data['fee_item_id'], 'amount_kobo' => $this->kobo($data['amount_naira']),
            'is_required' => $request->boolean('is_required'),
        ];
    }

    private function generationData(Request $request): array
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')], 'due_date' => ['required', 'date'],
            'scope_type' => ['required', Rule::in(['school', 'section', 'level'])], 'scope_id' => ['nullable', 'integer'],
        ]);
        AcademicTerm::findOrFail((int) $data['term_id']);
        if ($data['scope_type'] !== 'school') {
            if (! ($data['scope_id'] ?? null)) {
                throw ValidationException::withMessages(['scope_id' => 'Choose a section or level.']);
            }
            $scopeModel = $data['scope_type'] === 'section' ? Section::class : AcademicLevel::class;
            $scopeModel::findOrFail((int) $data['scope_id']);
        } else {
            $data['scope_id'] = null;
        }

        return $data;
    }

    private function copyData(Request $request): array
    {
        $data = $request->validate([
            'source_term_id' => ['required', 'integer', Rule::exists('terms', 'id')],
            'target_term_id' => ['required', 'integer', Rule::exists('terms', 'id'), 'different:source_term_id'],
            'scope_mode' => ['required', Rule::in(['all', 'one'])],
            'scope_type' => ['nullable', Rule::in(['level', 'class', 'department'])],
            'scope_id' => ['nullable', 'integer'],
        ]);
        AcademicTerm::findOrFail((int) $data['source_term_id']);
        AcademicTerm::findOrFail((int) $data['target_term_id']);

        return $data;
    }

    private function catalog(): array
    {
        return [
            'terms' => AcademicTerm::with('session')->latest('start_date')->get(),
            'sections' => Section::orderBy('name')->limit(100)->get(),
            'levels' => AcademicLevel::orderBy('name')->limit(100)->get(),
        ];
    }

    private function kobo(string $naira): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '', $naira), 2), 2, '');
        $amount = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount_naira' => 'Enter a positive amount in naira.']);
        }

        return $amount;
    }
}
