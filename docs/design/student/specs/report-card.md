# Spec: Report card — `screens/report-card.html`

States: Normal · No remark column · Some subjects approved · Locked (fees unpaid).

## Today
`results.mine.show` → `ResultCardController::myResult` renders the **staff view** (`results/card.blade.php`):
- a "Results workspace" link a student cannot use,
- the **class broadsheet with every classmate's name, number of subjects and average**,
- staff comment forms hidden by `@can`.
When payment access fails, `ResultPaymentAccess::authorize` throws and the student sees a raw 403.

## Changes (needs a decision from you: see "Privacy" below)
1. Make a student-only view (or a `mode` flag) that shows: header (name, term, class), facts (**position "4th of 32"**, **own average**, subject count), the subject table, the three comments, next-term date, and an **Open A4 PDF** button (`results.mine.pdf`, opens inline).
2. **Components** column (`CA1 18 · CA2 17 · Exam 46`) comes from `result_items` as the PDF already does. Remark column only when `show_subject_remarks`.
3. **Privacy:** the design shows only the student's own position and average and **no classmate names or averages**. The PDF already shows position only. If you want students to see the class broadsheet, say so; I'd remove it.
4. **Locked state:** catch the payment exception and render the locked card with Pay (`payments.mine.begin`) instead of a 403.
5. Comments show the effective text (personalised, else term default), same as the PDF. Hide empty ones.
6. Phone: each subject becomes a card-row (subject + grade, total + %, remark, components). No horizontal scroll.
7. The one-A4-page rule applies to the PDF only; the screen may be longer.


## Update (batch 3): head title label
The second comment label follows the section's head title: "Principal's comment" (Secondary School) or "Head teacher's comment" (Primary School). It is data, not hardcoded. The first label is "Form teacher's comment", the third "Proprietor's comment". Storage and permission names do not change. See `specs/parent-report-card.md` for the NEW section data. The design file now has a "Primary: Head teacher" state.
