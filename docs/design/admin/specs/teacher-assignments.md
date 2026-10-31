# Spec: Teacher assignments — `screens/teacher-assignments.html`

States: By teacher (editing) · Review changes · Saved · Teacher with none · **Many subjects (22)** · By class (editing) · By class: review · Error (no teacher) · Saving · No permission. **Head version (`screens/head-teacher-assignments.html`)**: the same states plus **No section assigned (locked)**.

## Who can open
`manage_result_settings` (what `results.teacher-assignments.store` requires today). **Decided (owner, 2026-10-18): the Admin assigns in any section and a head assigns for their own section only** (replaces the 2026-10-11 "Admin only"). Codex names a section-scoped permission (log it). The server refuses any class outside the actor's section. The head version is the Admin screen with the section fixed (no switcher, section name shown, that section's classes only, locked state "No section assigned"). A teacher who works in both sections shows only the head's own section's assignments on that head's screen.

## Today
`teacher_subject_assignments` stores user, class and subject; assignments are
unique and persistent, not per Term. The By class state also shows each Class
Arm and its current form teacher.

## Design (D4)
- **By teacher:** pick a teacher; a class × subject **matrix** of **only the pairs the class actually has** (other cells show a dash and cannot be ticked). Pending changes are highlighted (green add, red remove). **Review changes** shows "Will add 2 · Will remove 1 · Unchanged 3", lists each change, and says removed assignments **never delete results already entered**. Confirm, then a Saved notice with counts.
- **By class:** pick a class; one row per subject with a **single** teacher select; unassigned subjects are flagged "No teacher yet". Same review. **Decided:** the UI offers **one teacher per class subject** (the unique key would allow more; the UI does not). A replacement is selected directly in one reviewed save. The review shows the count of entered Results that stay unchanged.
- **Many subjects (20+):** the matrix scrolls sideways inside its own region (focusable, labelled for screen readers) with the **class column frozen**, the **header row sticky** and the level labels pinned. A line above the matrix tells people to scroll sideways. **Phones** switch to one card per class listing **only that class's subjects** (not-offered cells are hidden), so 22 columns never become 22 rows.
- Assignments persist from term to term.

## Dependent fields
Only subjects in the class's `class_subjects` are offered. Teacher must be chosen first. Review is disabled with no changes. An active teaching User belongs to a Section through `section_user`, a subject assignment there, or a form-teacher arm there. Admin sees that Section's teachers first and an **Other teachers** group; a Head sees only their Section's teachers.

## Server rules
The server accepts only pairs present in `class_subjects` and ignores anything else (hidden cells are not a boundary). Idempotent. Inactive and non-teaching Users are refused. A Head cannot assign, remove or replace outside their Section, including with edited IDs. Removal/replacement preserves Results and is audit-logged with previous/new teacher IDs.

## Tests (batch 4 and owner fixes)
Pairs outside `class_subjects` are rejected; run twice, no duplicates; removing
an assignment leaves Results untouched. Trust coverage also proves Section,
active-status and teaching-permission eligibility, dual-Section eligibility,
cross-Section removal/replacement refusal and assignment audit metadata.
