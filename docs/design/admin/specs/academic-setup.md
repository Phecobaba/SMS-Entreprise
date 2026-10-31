# Spec: Edit and delete for the academic setup — `screens/academic-levels.html`, `academic-subjects.html`, `academic-sessions.html`, and class arms on `academic-classes.html` / `head-classes.html`

Added 2026-10-18; **revised 2026-10-23 (Section ownership, head variants)**. Small items: no new tables are invented; Codex names any columns it needs and logs them.

**Implementation note (2026-10-24):** the Admin and Head Levels/Subjects
variants, read-only Sections Level summary, and grouped Admin Sessions/Terms
view are aligned with the re-issued references. Section ownership remains
immutable and server-scoped as specified below.

States, Levels (Admin): Normal · Add a level · Error (no section chosen) · Edit a level · Error (duplicate name) · Delete: allowed · Delete: blocked · No levels yet · No permission.
States, Subjects (Admin): Normal · Add a subject · Error (no section chosen) · Edit a subject · Error (duplicate name) · Delete: allowed · Delete: blocked · No subjects yet · No permission.
States, Sessions & terms: Normal · Edit a session · Edit a term · Error (duplicate name) · Delete a session: allowed / blocked · Delete a term: allowed / blocked · No sessions yet · No permission.
States, Arms (added to Classes, Admin and head): Edit an arm · Arm: duplicate name · Edit an empty arm · Delete an arm · Delete an arm: blocked.

States, Levels and Subjects (head, `head-levels.html`, `head-subjects.html`): Normal · Add · Edit · Error (duplicate name) · Delete: allowed · Delete: blocked · No levels / subjects yet · No section assigned (locked) · No permission.

## Who can open
**Decided (owner, 2026-10-18):** the **Admin** edits and deletes in any section; a **head** edits and deletes **class arms in their own section only** (where heads already manage classes). Levels, subjects, sessions and terms are school-wide, so they are **Admin only** (this follows from the owner's wording; Codex confirms). The server refuses a head acting on an arm outside their section.

**Amended by owner, 2026-10-23 (supersedes the lines above and the earlier move allowance):**
- Every **Level and every Subject belongs to exactly one Section**, **chosen when it is created and never changed**. There is **no move** for anyone, Admin included. A wrong one is deleted and recreated.
- **Admin** creates, edits (name only) and deletes Levels and Subjects in any Section. The Admin **create** sheet has a required **Section** field; the edit sheet shows the Section as read-only text.
- **Heads** create, edit and delete Levels and Subjects **in their own Section only** (`manage_section_levels`, `manage_section_subjects`). There is **no Section switcher** and the **create sheet has no Section field** ("It will be added to Secondary School"). The server takes the Section from the head's assignment and refuses any other, even with edited ids.
- Sessions, terms and Departments stay Admin-only.

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


## Added 2026-10-23: screens and rules
| Screen | File | Notes |
|---|---|---|
| Levels (Admin) | `screens/academic-levels.html` | Cards per Section. **Add a level** sheet has a Section select ("Choose carefully. A section can't be changed after you save."); its error state is a duplicate name. Edit sheet: name field and the Section as read-only text with "delete it and create it again". |
| Subjects (Admin) | `screens/academic-subjects.html` | One list with a **Section tag on every row** and a **Section filter** (the same name can exist once per Section, so Mathematics appears twice). Same create and edit sheets as Levels. The duplicate-name error names the Section. |
| Levels (head) | `screens/head-levels.html` | Own Section only, with a note "You see and change only Secondary School". The Add sheet has a name only ("It is added to Secondary School"); the edit sheet shows the Section read-only. |
| Subjects (head) | `screens/head-subjects.html` | Own Section only. |

- **Navigation:** the head's sidebar Academics group gains **Levels** and **Subjects** (order: Classes, Levels, Subjects, Bulk subjects, Teacher assignments, Promotion, as in the 2026-10-20 references), and the phone **More** list gains Levels, Subjects, Bulk subjects, Teacher assignments and Promotion (it had only Staff and Notifications). See `shell-and-navigation.md`.
- **Admin sidebar:** Bulk subjects, Teacher assignments and Promotion are links under Academics on every Admin screen (as in the 2026-10-20 references; Codex has built them).
- **Supersedes** the Admin-only "change a section" rule in `academic-setup-addendum.md` (2026-10-18). That file is kept with a revision note.
- **Delete rules are unchanged** (level blocked while it has classes; subject blocked while assigned, taught or has results). Names are unique **per Section** for Subjects and per school for Levels (`docs/schema.md`).

### Tests (trust-sensitive)
No request changes the Section of an existing Level or Subject (an update carrying a different Section is refused for Admin and head); a head cannot create, edit or delete a Level or Subject in another Section, even by editing ids; a head's create request ignores any posted Section; an Admin create without a Section is refused; the same Subject name is allowed in two Sections and refused twice in one (the error names the Section); a head with no Section assigned sees only the locked state.

### Open (do not decide)
- A head assigned to **more than one Section** (assignments are many-to-many): the design assumes one. With two, the create sheet would need a Section choice.
- A Level or Subject in the wrong Section that already has classes, teachers or results cannot be deleted, so it cannot be corrected (see `academic-sections.md`).
