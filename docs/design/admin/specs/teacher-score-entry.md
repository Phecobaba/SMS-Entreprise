# Teacher Results — Score entry

Status: **New design reference.** HTML: `../screens/teacher-score-entry.html`.

## Layout
Breadcrumb: My classes and subjects → assigned pair → pupil. Show pupil name, registration number when present, arm, the pair label `<Subject> · <Class>` and Term. Do not show Level or Section. Use a simple labelled field per assessment component, with its actual maximum and numeric range. Show calculated total preview and one clear primary **Save draft** action; keep **Submit for approval** separate. Provide **Back to pupils**.

The HTML example uses CA1 (10), CA2 (10), Assignment (10), Exam (70) **only as illustration**. The live implementation must load the actual Result template/components and maxima for this pupil/pair/Term, and must not hardcode the sample. Preserve server-side validation, authorization, submission, approval and result-lock behavior.

## States
- Draft: editable fields; Save draft and Submit for approval.
- Submitted: read-only, submitted-for-review explanation.
- Approved: read-only, approved/locked explanation.
- Returned for correction: if the existing workflow supports it, use the existing status and re-enable editing only when authorized. Do not invent a new status.

## Responsive and interaction
Two-column score inputs at 1280px, one column at 390px; labels always visible, 44px+ controls and actions, no page-level horizontal overflow. Use the shared Oakfield tokens and components. State toolbar is design-only; no fake saving.
