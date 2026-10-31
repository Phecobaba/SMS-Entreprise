# Spec: Staff Account (change password) — `screens/account.html` (Admin), `screens/head-account.html` (Head)

Batch 6. States: Normal · Wrong current password · Rules not met · Passwords differ · Saved. The Bursar and other staff roles use the same content inside their own shell (not drawn: same cards).

## Who can open
Every signed-in staff user, for their **own** account. Reached from **Account** in the sidebar foot and the phone More list.

## Stated current behaviour (Codex: confirm first)
Whether a change-password page already exists (Fortify or Breeze style), its rules, and whether other sessions are signed out.

## Design
- **Your account:** name, role, Section (head), username. Read-only; "To change your name, role or username, ask an Admin" (open: may staff edit their own username?).
- **Change password:** current password, new password, confirm, show/hide on each. Rules list with text and a symbol, never colour alone: at least 8 characters; not the same as the username; different from the current password (**proposals**, Codex aligns with the existing password rule).
- On success: a notice, and **other devices are signed out** (proposal).

## Tests
- Wrong current password is refused and throttled like login.
- A new password equal to the username or the current one is refused.
- Other sessions and remember tokens are revoked on change.
- The password, current or new, never appears in logs, audit rows or flash data. The audit row says "password changed" only.

## Open questions
Students and parents: can they change their own password later? `student/specs/profile.md` calls a password change on the profile page **optional**; this batch designs only the first-login screen and an Admin reset for them.
