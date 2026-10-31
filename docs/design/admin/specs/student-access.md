# Spec: Student sign-in and registration number — `screens/student-access.html`

Batch 6. States: Normal · No number permission · Change number · Number already used · Reset student: confirm · Reset student: shown once · Reset parent: confirm · Reset parent: shown once · Awaiting admission · Graduated · No permission.

## Who can open
Admin. The **registration-number change** is a separate section-scoped permission (decided 2026-10-17, name for Codex): the Admin, or the head of the Section the pupil is currently in. This is a sub-page of the student record; I have not seen today's record page, so Codex places these cards there.

## Stated current behaviour (Codex: confirm first)
Whether `students` has a registration number column today (my plan assumes **no**: NEW), where it is printed or searched (receipts, report cards, invoices, login), and how parents are linked.

## Design
- **Registration number:** current number, **earlier numbers** (kept, searchable), **Change number** (sheet: current read-only, new number, unique across the school, every change logged: old, new, who, when). Wording reuses the existing Incoming sheet.
- **Student sign-in:** username, email, status, last sign-in, **Reset password** (confirm sheet, then the one-time panel).
- **Parent logins:** each linked parent with their username, the children they cover, and **Reset password**. A reset signs the parent out for **all** their children.
- **Graduated:** the student may still sign in with read-only access (past report cards, paid receipts, payment-gated Attendance for the last 30 recorded school days); **Awaiting admission:** past report cards and receipts only. Reset is still allowed.

## Rules
Reset: new hash, `must_change_password`, all sessions and remember tokens revoked, audited without the password; same credential handling as `staff-add.md`. The number never encodes the class.

## Tests
- A head cannot change the number of a pupil outside their Section, even by editing ids.
- A duplicate number (including an old number in history, if Codex confirms that is a clash) is refused.
- Every change writes a log row and a history row; the old number still finds the student in search.
- Resetting a parent signs out that parent on every device and keeps all links.
- Credentials never logged or stored in plain text.

## Open questions
- **Who edits a registration number while a pupil is Awaiting admission.** The 2026-10-17 note proposes only the Secondary head and the Admin (the Primary head cannot know the Secondary sequence). Not decided; the screen shows it as a proposal.
- Whether a number kept in history blocks reuse.


## Follow-up changes — Batch 6 reissue (2026-10-31)

# Student Access — Batch 6 follow-up

Registration number/history, username not set, candidate email retained, reset password. Admin any pupil; Head current Section; Awaiting admission Admin-only. Historical number collision error. Temporary credentials shown once, no-store, must-change-password on reset.

Design-only HTML is a reference; Backend Agent must confirm API and permissions before Codex implementation.


### Confirmed correction

One combined Student page: identity form (name, email, username), current sign-in and number, number history, and Admin password reset. The standalone student-record reference is retired from this package. For Awaiting admission, number edits remain Admin-only.

### Latest implementation alignment

Current state supersedes earlier proposed subpage behavior: ONE combined Student page is BUILT in Batch 6B.
