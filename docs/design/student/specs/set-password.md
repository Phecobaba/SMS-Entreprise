# Spec: Set your password (first sign-in, every role) — `screens/set-password.html`

Batch 6. States: First sign-in · After an Admin reset · Rules not met · Passwords differ · Same as temporary · Saving · Done. Guest-style layout, same as `login.html`.

## Who sees it
**Any account whose password was made by someone else**: staff, students, parents (and candidates, if the admissions flow creates their login: open question), at the first sign-in and again after every Admin reset. Triggered by `users.must_change_password` (NEW).

## Stated current behaviour (Codex: confirm first)
Whether any forced-change or change-password route exists, and the password rule in use today.

## Design
- Heading **Set your password**; a sentence by state ("Welcome, Amara..." or "The school office reset your password..."); **New password** and **Confirm new password** with show/hide (44px, `aria-pressed`); a rules list with a text and symbol per rule (at least 8 characters; not the same as the username; different from the temporary password: **proposals**); one primary button; "You can't skip this step. Not you? Sign out."
- On success: the flag is cleared, the session id is regenerated, **other sessions are revoked**, and the user lands on **their own dashboard** (student, parent, or staff role dashboard).

## Rules
- While the flag is set, **every route except this page, sign-out and static assets redirects here**: pages, form posts, Livewire/AJAX endpoints, exam taking, payments, downloads.
- The new password cannot equal the temporary one. Throttle the form like login.
- Neither password is logged or flashed; the audit row says "password set".

## Tests (required)
- An account created by an Admin (staff, student, parent) cannot open any other page, including direct URLs and POSTs, until the password is changed.
- After an Admin reset the flag is set again and the old password fails.
- The temporary password is refused as the new password; rule failures are server-validated, not just client-side.
- After success the flag is cleared and the user is routed to the right dashboard for their role.

## Open questions
Candidates and admission accounts; whether the sign-in "Keep me signed in" token is cleared on change (proposal: yes).
