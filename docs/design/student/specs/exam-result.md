# Spec: Exam result — `screens/exam-result.html`

States: Answers + explanations · Answers only · Score only · No score shown · Opens later · Not released.

## Today
`cbt.result` → `ExamWorkflow::resultFor()` → `cbt/result.blade.php`. If the attempt is not Submitted or `isReleased()` is false it calls **`abort(403)`**, so a student who taps an unreleased result sees a bare 403 page.
The payload has `score`/`maximum` (null unless `show_score`) and an `answers` list with only `question`, `correct` (if `show_correct_answers`) and `explanation` (if `show_explanations`).

## Changes
1. **Replace the 403 with the "not released" states.** Render the page with a waiting state: `result_release_type = Scheduled` → "opens on {result_release_at}"; Manual or `requires_approval` without approval → "your teacher hasn't released this yet". Still 403 for someone else's attempt (`assertOwner`).
2. **Add the student's own answer** to each `answers` row (`selected` text and `was_correct`), only when `show_correct_answers` is on. Add `position` so rows keep exam order. Today the page cannot show what the student chose.
3. Hero shows score and percentage only when `show_score`. No pass/fail for internal exams (they have no pass mark).
4. "Not correct" filter chip works without a reload (client-side). Paginate the review at 10 per page for long exams.
5. Candidates (entrance exams) share this page: hide class/subject, and do not show pass/fail unless Admissions decides to release it (not designed).

## Rules
Never send `is_correct` for any option, only the correct option's text and only when `show_correct_answers`. A not-yet-released result must reveal nothing but the release time.
