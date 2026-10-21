# Spec: Parent report card — `screens/parent-report-card.html` (also updates `screens/report-card.html`)

States: Secondary school (Principal) · Primary school (Head teacher) · No remark column · Some subjects approved · Locked.

## Who can open
Proposal: the student themself or a linked parent. Staff keep their own view (`results.cards.show`).

## Today
`myResult` renders the staff view, including the **class broadsheet with classmates' names and averages**, and throws a raw 403 when locked. (See `specs/report-card.md`.)

## Design
- **Privacy:** only the child's own position ("4th of 32") and average. No classmates' names or averages. Same decision as the student.
- Subject table with the components column; phone shows card-rows. Open A4 PDF (`results.mine.pdf`).
- **Comment labels:** "Form teacher's comment", **"{head title}'s comment"**, "Proprietor's comment". The second label follows the **section's head title** ("Principal" for Secondary School, "Head teacher" for Primary School). It is data, not hardcoded.
- Locked: a neutral locked card and a Pay button; nothing about the results is rendered.

## NEW
- Section head title (e.g. a `head_title` on the section) and a way to derive a student's section: class arm → class → level → section. Defined by the Admin foundations brief; confirm names there.
- Keep existing column and permission names (`principal_comment`, `add_principal_comment`); only the **label** changes. Do not rename storage.
- The PDF's "Principal's Comment" row should use the same label.

## Dependent fields
Remark column only when the template has `show_subject_remarks`. Locked card replaces everything when payment access fails.

## Open questions
- Does the PDF also change its label for Primary School? (Assumed yes, the same data.)
