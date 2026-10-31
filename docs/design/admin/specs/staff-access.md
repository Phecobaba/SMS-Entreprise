# Spec: Staff sign-in and access (reset, role, Section) — `screens/staff-access.html`

Batch 6. States: Normal · Not signed in yet · Change role · Role above creator · Reset: confirm · Reset: shown once · Higher role: view only · Own account · No permission.

## Who can open
Admins (permission for Codex to name). This is a sub-page of one staff member; I have not seen today's Staff HR detail, so **Codex places these cards where it fits** and keeps the rest of that page.

## Stated current behaviour (Codex: confirm first)
How an Admin changes a staff role or Section today; whether any reset exists; how sessions and "remember me" tokens are stored.

## Design
- **Account** list: username, email, role, Section, status (**Active**, **Has not signed in yet**, **Must set a new password**), last sign-in.
- **Reset password** (confirm sheet, then the one-time panel as in `staff-add.md`). The sheet says what happens: new temporary password shown once, signed out everywhere, must choose a new password at next sign-in. This is the **forgot-credentials path** (there is no email reset).
- **Change role or Section** (sheet). Roles above the changer's own are not offered; a forced one is refused ("You can't give a role above your own").
- **Higher role:** if the target's role is above the viewer's, the account is view-only: no reset, no role change.
- **Own account:** no reset here; use Account (`account.md`).

## Rules
- A reset sets `must_change_password`, replaces the hash, **revokes every session and remember token**, and is audited (who reset whom, when; never the password).
- Same credential-handling rules as `staff-add.md`.

## Tests (required)
- Reset by a lower role on a higher-role account is refused (forged request included). Own-account reset is refused.
- After a reset the old password fails; other devices are signed out; the next sign-in lands on set-password.
- The new temporary password is not in any log, audit row, session, queue or flash.
- A role change above the changer's own is refused; the failure creates no audit row that implies success.
- Head Section assignment writes only to `section_user` for that user.

## Open questions
Role order; multi-Section heads; whether an Admin may reset another Admin.
