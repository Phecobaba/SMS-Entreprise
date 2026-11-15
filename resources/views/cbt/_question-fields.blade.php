@php
    $fieldOptions = isset($question) ? $question->options->values() : collect(range(0, 3))->map(fn () => null);
    $initialCorrect = $fieldOptions->map(fn ($option) => (bool) ($option?->is_correct ?? false))->values();
    if (! isset($question)) $initialCorrect[0] = true;
    $initialMarks = $fieldOptions->map(fn ($option) => (int) ($option?->marks ?? 1))->values();
@endphp
<div class="grid gap-3" x-data='{ correct: @json($initialCorrect), marks: @json($initialMarks), get total() { return this.correct.reduce((sum, yes, index) => sum + (yes ? (Number(this.marks[index]) || 0) : 0), 0) }, get picks() { return this.correct.filter(Boolean).length }, get valid() { return this.picks > 0 && this.picks < this.correct.length } }'>
    <label>Question<textarea required maxlength="1000" name="text" class="mt-1 min-h-24 w-full rounded border p-2">{{ $question->text ?? '' }}</textarea></label>
    @foreach($fieldOptions as $index => $option)
        <fieldset class="grid gap-2 rounded border p-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
            <label>Option {{ $index + 1 }}<input required maxlength="300" name="options[{{ $index }}]" value="{{ $option?->text }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
            <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="correct_options[]" value="{{ $index }}" x-model="correct[{{ $index }}]"> Correct</label>
            <label x-show="correct[{{ $index }}]">Mark<input type="number" min="1" step="1" name="option_marks[{{ $index }}]" x-model="marks[{{ $index }}]" :required="correct[{{ $index }}]" :disabled="!correct[{{ $index }}]" class="mt-1 min-h-11 w-24 rounded border p-2"></label>
        </fieldset>
    @endforeach
    <p class="text-sm" :class="valid ? 'text-slate-700' : 'text-red-700'"><strong x-text="total"></strong> marks total · pupil may choose up to <strong x-text="picks"></strong> <span x-show="!valid">· Choose at least one correct and one wrong option.</span></p>
    <label>Explanation (optional)<textarea name="explanation" class="mt-1 min-h-24 w-full rounded border p-2">{{ $question->explanation ?? '' }}</textarea></label>
    <button class="min-h-11 rounded border px-4 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!valid">{{ isset($question) ? 'Save question' : 'Add question' }}</button>
</div>
