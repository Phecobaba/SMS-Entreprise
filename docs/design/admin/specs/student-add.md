# Spec: Add a student — `screens/student-add.html`

Batch 6. States: Normal (automatic number) · Typed number · Number already used · Username taken · Link existing parent · Created: new parent · Created: existing parent · No permission.

## Who can open
Admin with the student-create permission (name for Codex). Heads do **not** add students (not in this batch).

## Stated current behaviour (Codex: confirm first)
I have not seen today's admission or add-student form. Codex lists which student fields exist (date of birth, gender, guardian columns...) and **keeps them**; this screen shows only the fields Batch 6 touches: name, class, registration number, username, email, status, parent. Also confirm: auto-invoicing at enrolment when a fee schedule exists (built per the payments work), and the Not invoiced list for the rest.

## Design
- **Full name**, **Class** (grouped by Section), **Registration number** (Automatic, or Type one), **Username** (suggested, editable), **Student email (optional)**, **Status** shown as Active (a student added directly is Active; **proposal**).
- **Registration number** follows the **Section of the chosen class** (per-Section prefix, `registration-numbers.md`) and never the class itself. Automatic shows the next number as a preview; it is **fixed at save**, and two people saving at once must not get the same number (unique constraint plus retry). Type one needs the registration-number permission and a number that is unique across the school (checked live and again on save).
- **Parent or guardian:** *Add a new parent* (name, username, optional email) or *Link an existing parent* (search). A parent has **one login for all their children** (parent_student pivot): linking an existing parent makes **no new credentials** and the child appears in their switcher.
- On save: one transaction creates the student, the optional parent and the links; the auto-invoice (if any) follows the existing rule. Credentials appear **once**: the student, and the parent only if newly created. Same rules as `staff-add.md` (hash only, never logged, `no-store`, forced change at first sign-in).

## NEW data
Registration number column and history (see `student-access.md`), `must_change_password`. The student status set stays Active, Awaiting admission, Graduated.

## Tests
- Two simultaneous saves never share a registration number; a typed duplicate is refused school-wide.
- The number's prefix comes from the class's Section; no class or level text appears in it.
- Student and parent created together or not at all (a failure after the student is saved rolls back).
- Linking an existing parent creates no second parent and no new password.
- Credentials never stored in plain text or logged; both accounts are flagged to change password at first sign-in.
- A student with no email can sign in with the username.

## Open questions
Who edits a registration number once a pupil is Awaiting admission (see `student-access.md`); whether a typed number may reuse an **old** number kept in history; what a parent with several children signs in with (`sign-in-rules.md`); whether admission candidates who become students receive an account here or through the admission flow (not seen).
