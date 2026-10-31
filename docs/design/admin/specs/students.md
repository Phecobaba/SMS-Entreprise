# Spec: Students list changes — `screens/students.html`

Batch 6. **Proposal only.** I have **not seen today's Students page** (the sidebar already links `students.html`). Keep the current page. What changes:
- Two header buttons: **Import students** (`students-import.md`) and **Add student** (`student-add.md`).
- Under each name, the **registration number** (monospace) and a link to **Sign-in and number** (`student-access.md`).
- A **Section** filter (Admin) and a **Status** filter (Active, Awaiting admission, Graduated). Search by name or registration number (the old numbers in history also match).
States: Normal · Filtered: Awaiting admission · No results · No students yet · No permission. A pager appears only when there is more than one page.

## Stated current behaviour (Codex: confirm first)
What the list shows and filters today; whether Awaiting admission and Graduated students appear in it.

## Tests
Status filter never leaks other Sections' students to a head if a head ever gets this page (heads have none today); search by an old registration number finds the student.


## Follow-up changes — Batch 6 reissue (2026-10-31)

# Students — Batch 6 follow-up

NEW Students list (not a retrofit). Rows originate in Admissions candidate conversion; status/number/Section/Class/username shown. Row links to Student record; name/email/username small edit. Converted candidates keep their email and chosen password, and can be missing username. Admin resets require first-login password change. Admin-created accounts also require first-login change. Search old registration numbers; paginate growing lists. Direct Add student and Import remain separate proposals and not existing app functionality.

Design-only HTML is a reference; Backend Agent must confirm API and permissions before Codex implementation.


### Confirmed correction

Batch 6B Students list and Add student are built. List header must show Add student. Remove all “not implemented” copy. Row actions open the one combined Student page, not a second Student record screen.

### Latest implementation alignment

Current state supersedes earlier proposal-only statements: Students list and Add student are BUILT in Batch 6B.
