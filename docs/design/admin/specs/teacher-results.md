# Teacher Results — My classes and subjects

Status: **New design reference; not implemented by this package.** Role: Teacher. Files: `../screens/teacher-results.html`, `teacher-results-pair.html`, `teacher-score-entry.html`.

## Purpose and information architecture
Replace the flat Results workspace entry point for Teachers with assigned subject–class cards. Do not change Admin or Head Results workspaces. The first heading is **My classes and subjects**.

Each card represents one distinct teacher assignment `(subject, class)`; its exact display format is **`<Subject> · <Class>`** (e.g. "Mathematics · JSS 2"). Class names are unique school-wide and the class already identifies its Level and Section, so neither is shown. Show a card for every assignment the Teacher holds, in whichever Sections they fall. A subject Teacher's class assignment covers **all arms** of that class. Do not create one card per arm. A pupil studies every subject offered by their class; no elective filter or pupil-subject opt-in.

Cards show current Term, total pupil count, and **Draft / Submitted / Approved** result counts. Keep Not started pupils discoverable on the pair page, rather than implying that these three counts always add up to the class total. Each card has a 44px+ **Enter scores** action leading to its pair page.

## Form teacher: My class
Show a separate **My class** card only when the authenticated Teacher is the assigned form teacher of a class arm. Label the specific class and arm. Its destination shows **approved** class-wide subject results and compiled report cards **for that arm only**, not all arms, not the Teacher's other subjects or other Sections. Plain subject Teachers never see the card or its routes. The design preview includes a form-teacher state, approved state and no-approved state; sample data is not a permission grant.

## Empty and loading states
- No subject assignments: **No subject assignments yet**; direct the Teacher to school office/Section Head. Do not show the all-school pupil table.
- Form teacher with no approved results: **No approved results yet**; no fake cards or counts.
- No form-teacher assignment: omit My class entirely.
- No term selected or configured: show a plain explanation and do not invent counts.

## Trust and implementation
The server must derive assignments from authenticated teacher + subject/class associations; do not trust a posted Section, class, subject, or arm ID. A forged pair URL must not expose pupils, scores, or CSV. Form-teacher access is separately verified against the assigned arm. Counts and all routes must be tenant- and assignment-scoped. Existing result approval/locking and report-card rules remain unchanged.

## Responsive and visual
Reuse the existing Oakfield teal/cream/ochre tokens, serif headings, shared cards, pills, sidebar and mobile shell. At 1280px: two-column assignment cards. At 390px: one column, no page-level horizontal overflow, 44px minimum tap targets. The bottom **State** control is design-only and is not part of the Laravel UI.
