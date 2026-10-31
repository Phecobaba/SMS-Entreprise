# Spec addendum: head Levels and Subjects, subjects belong to one section — `screens/head-levels.html`, `screens/head-subjects.html`, `screens/academic-subjects.html`

**Revised 2026-10-23:** the owner decided a Level or Subject's Section is **chosen at creation and never changed, by anyone**. Every line below that lets the Admin change or move a Section (including the "Section field in the edit sheet" and the open question about moving) is **superseded**; the edit sheets now show the Section read-only. See `academic-setup.md` ("Amended by owner, 2026-10-23").

Added 2026-10-18 after the owner's answers. It extends `academic-setup.md` (which holds the head rule) without repeating it.

States, head Levels: Normal · Add a level · Add: duplicate name · Edit a level · Error (duplicate name) · Delete: allowed · Delete: blocked · No levels in the section · No section assigned (locked).
States, head Subjects: the same, for subjects.

## Decided (owner, 2026-10-18)
- **Every level and every subject belongs to exactly one section.** There is no shared level and no shared subject. The same name in two sections is two subjects (Mathematics in Primary School and Mathematics in Secondary School), and a name must be unique **within its section**.
- A **head** edits and deletes levels, subjects and arms **in their own section**. ~~Changing a level's or subject's section stays Admin only.~~ (Superseded 2026-10-23: nobody changes it.) Sessions and terms stay Admin only.
- The **current session or term cannot be deleted.** Session and term dates **may overlap**; the only rule is **end after start**. There is **no level-order control**. No price uplift on fee copy.
- Codex builds the Blade views. `notifications.url` is built.

## Design
- **Head Levels and Head Subjects** are the Admin screens with the section fixed: no section switcher, only the head's section listed, a note saying the head sees only their section, and a read-only **Section** line in the edit sheet. **Heads can also add levels and subjects in their own section** (decided 2026-10-18): the Add sheet has a **name only and no Section field** ("It is added to Secondary School"), and a duplicate name inside the section is refused. The Admin's Add sheet has a Section field. The blocked and allowed delete states match the Admin's. A head with no section sees the locked state.
- **Admin Subjects now shows the section** on every row, has a **Section filter**, and a **read-only Section line** in the edit sheet (the Add sheet has the Section field); the duplicate-name error names the section ("already exists in Secondary School"). The Admin Levels sheet already had a Section field.
- **The earlier Subjects design assumed school-wide subjects** (one list across the school, no section). That was wrong and is corrected here.
- Head sidebar: Academics group now lists **Classes, Levels, Subjects, Bulk subjects, Teacher assignments, Promotion**. Admin sidebar: **Bulk subjects, Teacher assignments and Promotion** are now links under Academics.

## NEW
A **section** on subjects (a subject belongs to exactly one section). **Verify first:** whether `subjects` already has a section or level link today and how `class_subjects` and teacher assignments reference subjects; whether any subject is currently used in classes of both sections (data to be split before this is enforced).

## Tests (trust-sensitive)
A head cannot edit or delete a level, subject or arm outside their section, even by editing ids (the Admin can); no one can change a level's or subject's section (an update carrying a different section is refused); a subject can be assigned only to classes in its own section (server-side); duplicate names are refused within a section and allowed across sections; the current session and term cannot be deleted; end must be after start.

## Open questions (do not decide)
- ~~Moving a subject or a level to another section while it is in use~~ (closed 2026-10-23: no moves).
- **Likely stale designs to review, not changed:** Bulk subject assignment and Teacher assignments should offer only subjects of the chosen level's or class's section; the Admin screens currently list subjects without a section.
