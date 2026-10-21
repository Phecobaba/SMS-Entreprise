# Spec: Edit and delete for the academic setup — `screens/academic-levels.html`, `academic-subjects.html`, `academic-sessions.html`, and class arms on `academic-classes.html` / `head-classes.html`

Added 2026-10-18. Small items: no new tables are invented; Codex names any columns it needs and logs them.

States, Levels: Normal · Edit a level · Error (duplicate name) · Delete: allowed · Delete: blocked · No levels yet · No permission.
States, Subjects: Normal · Edit a subject · Error (duplicate name) · Delete: allowed · Delete: blocked · No subjects yet · No permission.
States, Sessions & terms: Normal · Edit a session · Edit a term · Error (duplicate name) · Delete a session: allowed / blocked · Delete a term: allowed / blocked · No sessions yet · No permission.
States, Arms (added to Classes, Admin and head): Edit an arm · Arm: duplicate name · Edit an empty arm · Delete an arm · Delete an arm: blocked.

## Who can open
**Decided (owner, 2026-10-18):** the **Admin** edits and deletes in any section; a **head** edits and deletes **class arms in their own section only** (where heads already manage classes). Levels, subjects, sessions and terms are school-wide, so they are **Admin only** (this follows from the owner's wording; Codex confirms). The server refuses a head acting on an arm outside their section.

## Design
- Each list row has **Edit** (a sheet) and **Delete**. Arms are edited by tapping the arm chip on its class row; the sheet has **Delete this arm**.
- **Delete is blocked, never silently refused**: the sheet says what exists, in plain words ("JS1 A has 12 students, results for 2 terms and attendance records, so it can't be deleted") and what to do instead. When nothing exists, a confirmation names what is removed.
- **Blocking rules (decided):** a **level** is blocked while it has classes. A **class arm** is blocked while it has students (any status), results, attendance or invoices. A **subject** is blocked while it is assigned to classes or teachers or has results. A **session or term** is blocked once results, attendance or invoices reference it; deleting a session with no references removes its terms with it. Include timetable entries wherever a timetable exists (Codex checks).
- Renaming and changing dates never change records already made. Duplicate names are refused inline.
- The Admin's Academic structure tab bar gains **Levels**, and its **Sessions & terms** and **Subjects** tabs now open these screens. The Sections and levels screen is otherwise unchanged.

## NEW
Nothing is invented. A blocked-delete check that returns **what exists with counts** (for the message). If Codex needs an audit entry for edits and deletes, it names and logs it.

## Verify first (record Confirmed or Different with file and line)
Which tables reference a level, arm, subject, session and term (students, class arms, class subjects, teacher assignments, results, attendance, invoices, exams, any timetable) and their foreign-key delete behaviour (restrict or cascade); whether `/academics/setup` already offers edit or delete for any of them; how the current session and term are stored.

## Tests (trust-sensitive: deletion refuses data loss)
A delete is refused **server-side** (not just hidden) whenever any referencing record exists, including by a direct request, and succeeds when none exist; the refusal lists what exists; a head cannot edit or delete an arm outside their section; renaming or re-dating changes no results, attendance or invoices; deleting a session with no references removes its terms and nothing else.

## Open questions (do not decide)
- Whether the **current** session or term may be deleted when nothing references it (the design blocks nothing extra; a proposal is to refuse deleting the current one).
- Whether session or term dates may overlap.
- How levels are ordered inside a section (the design has no order control; nobody types an order number).
- Whether a head may edit or delete levels or subjects (the design says no).
