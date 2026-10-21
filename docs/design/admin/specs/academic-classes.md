# Spec: Academic structure, Classes — `screens/academic-classes.html` (Admin), `screens/head-classes.html` (head)

Re-issued 2026-10-18 for the owner's decisions: **nobody types an order number**, and **heads write within their own section**.

States, Admin: All sections · Primary School · Secondary School · School with no departments · No classes · Add a class · Edit a class · Error (duplicate) · Error (same step, same department) · Adding · Add (no departments) · Delete: confirm · Delete: blocked · Not arranged yet · No permission.
States, head: Normal · School with no departments · No classes yet · Add the first class · Add a class · Edit a class · Error (duplicate) · Error (same step, same department) · Adding · Delete: confirm · Delete: blocked · Not arranged yet · No section assigned (locked).

## Who can open
**Decided (owner, 2026-10-18):** the **Admin** creates, edits, moves and deletes classes in **any section**; a **head** does the same **only in their own section**. This replaces the 2026-10-11 "heads are read-only on classes". Admin: `manage_academics`. For heads Codex names a section-scoped class-management permission (proposal, log the name). The **server** refuses any create, edit, move or delete on a class whose level is outside the actor's section; hiding buttons is not enough. A head with no section assigned sees the locked state and no data.

## Today
Classes and arms are created on the single `academics` page; there is no section, department, position or list screen. (Foundations A built the Classes list without the order input.)

## Design
- Classes **grouped Section > Level**, listed **in order**. Each row: class name, department tag (only when departments exist), arm chips, student and subject counts, **Move up, Move down, Edit, Add arm, Delete**. **No order number is ever shown.**
- **Move up / Move down** moves the **whole step** (every class sharing the position, for example SS1 Science and SS1 Arts together) one place **inside its level**; it never crosses a level or section boundary. The parallel class shows "Same step as … Moves with it" and has no move buttons of its own. First and last steps have the matching button disabled. (A drag handle is allowed if it has a keyboard alternative; the buttons are the baseline.)
- **Add or Edit a class** (sheet): Level, Class name, Department (only when departments exist), **Comes after** (a select of the classes in that level, plus **First class in [level]**), and, **only when departments exist**, **Where does it go?**: **New step after [class]** or **Same step as [class]** (a parallel department class). A class on the same step **needs a different department tag** from every class already on that step (inline error otherwise). With no departments the choice is hidden. Arms: unchanged (JS1 A, B, C); arms are not ordered and follow their class.
- **Empty section:** the head sees "No classes yet" and **Add the first class**; the sheet has **no position question**, because the system places the class at the section boundary (right after the last class of the previous section, or first if it is the first section). A duplicate name within a level shows an inline error.
- **Delete a class:** a confirmation ("Delete SS3 Arts? … nothing else is lost"), or a **blocked** state naming what exists ("JS1 has 34 students and results for 2 terms, so it can't be deleted"), with a pointer to Promotion. **Proposal (not decided):** deletion is blocked while the class or any arm has **students (any status), results, attendance records or invoices**.
- **Not arranged yet:** until the person saves the first-time arrangement (see `classes-arrange.md`), the list shows a notice "Your classes have not been arranged yet" with the button **Arrange your classes**. The list order shown is a best guess only.
- The Admin has the section switcher (2+ sections only); the head has no switcher and sees only their section. Section name is in the heading. Sample class names (JS1 to SS3, Primary 1 to 6) are illustrative: classes are school data.

## NEW
- A **position number** on the class (Codex names it and logs it). Stored, never typed or shown. One school-wide sequence (decided 2026-10-17); **classes at the same step share a position**. Arms carry no position.
- A **classes arranged** marker per section (who and when; Codex names it). See `classes-arrange.md`.

## Rules for the build (Codex)
- **New step after X:** the new class takes the position immediately after X's step; later steps shift (renumber in one transaction, no gaps required). **Same step as X:** it takes X's position, only if its department differs from every class already at that position.
- **First class in [level]:** placed right after the previous level's last class in the same section; in the first level of a section, at the section boundary.
- **Move up/down:** swaps the whole step with the adjacent step **in the same level**.
- Editing a class's level re-places it through **Comes after**. Position changes never alter arms, students, results or invoices.
- The position drives the suggested next class in Promotion, which stays blocked until the arrangement is saved.

## Dependent fields
Department tag, filter and field: only when departments exist. **Where does it go?** only when departments exist and a "comes after" class is chosen. Section switcher: Admin with 2+ sections only. Everything a head sees and writes is filtered to their section server-side.

## Tests (trust-sensitive)
A head cannot create, edit, move or delete a class outside their section, even by editing ids (the Admin can); the **Comes after** list returns only classes the actor may see; a class on the same step with the same department is refused; moving a step swaps all classes sharing the position and nothing else; a class with students (any status), results, attendance or invoices cannot be deleted (proposal) and a class with none can; renumbering is transactional and positions stay unique per step; **Promotion is refused until the arrangement is saved**.

## Decided (owner)
Heads write within their own section; nobody types an order number; people choose positions ("Comes after", Move up and down, the first-time arrangement); parallel department classes share a step; arms are not ordered (2026-10-18). One school-wide sequence (2026-10-17).

## Proposals recorded (not decided)
- The deletion block above.
- If a head cannot place the first class of an empty section, **the Admin places it**; the system otherwise places it at the section boundary.

## Open questions (do not decide)
- Combinations the designs do not cover: more than two classes on one step; a class with no department on a step whose other classes have departments.
- Whether a head may change a class's level or only its name, department and position.
- Whether a deleted class's name may be reused immediately.


## Added 2026-10-18: class arms
Each arm chip is a button: it opens **Edit arm** (rename, **Delete this arm**). Duplicate names inside a class are refused inline. Deleting an arm is **blocked** while it has students (any status), results, attendance or invoices, with a message naming what exists, and allowed (with a confirmation) when empty. A head can do this only in their own section. See `academic-setup.md`.
