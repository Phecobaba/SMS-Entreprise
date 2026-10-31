# Spec: Add staff — `screens/staff-add.html`

Batch 6. States: Normal (Teacher) · Head: Section field · Username taken · Role above creator · Creator holds a lower role · Created: shown once · No permission.

## Who can open
Anyone holding the permission to create staff (name for Codex to match; proposed `create_staff`). Today's Admin staff form is **not seen by me**: this screen shows only the account-related fields. Employment, salary and the other Staff HR fields stay on the existing form (Codex: say where they go, in the same form or a second step).

## Stated current behaviour (Codex: confirm before building, tell me if wrong)
1. How a staff account is created today and what the form asks for. 2. Whether `users.username` exists and is unique, and whether it is case-sensitive. 3. Whether `section_user` (many-to-many) already stores head and teacher Section assignments. 4. Whether any role ranking exists. 5. How a temporary password is made today, if at all.

## Design
- Fields: **Full name**, **Role**, **Section** (Head: required; Teacher: optional), **Username**, **Email (optional)**. No email is fine: they sign in with the username (see `sign-in-rules.md`).
- **A creator cannot assign a role above their own.** The Role list offers only roles up to the creator's own; roles above are **absent**, not greyed. The server enforces it too: a posted higher role is refused with "You can't create an account with a role above your own." (state *Role above creator*). The same rule applies when a role is changed (`staff-access.md`).
- **Section** field: single choice per person in this design. Heads work only in their own Section (no switcher).
- On save, a **temporary password** is made on the server and shown **once** in a panel: name, role, username, password, a "Shown once" badge, **Copy password**, **Print slip**, and **Done** (enabled after "I have noted or printed these details", a proposal).
- The person must **choose their own password at first sign-in** (`set-password.md`).

## Credentials: how they must be handled
- Generated server-side from a secure random source (proposal: 10 or more characters, no look-alikes such as 0/O and 1/l). Stored **only as a hash**. The plain text exists only in the response to this one create request.
- **Never** written to: logs (request, query, exception), `audit_logs` or the Activity log, the session or flash data, queued job payloads, notifications, emails, or the database in any reversible form.
- The page that shows it is sent with `Cache-Control: no-store`; reloading or pressing Back does not show it again (state: after Done, or any later visit, there is no panel).
- Print slip = the browser printing the on-screen panel only. No server-made PDF containing the password.

## NEW data
- `users.must_change_password` (boolean, true on creation and on Admin reset). Codex names it.
- A role ranking source (config or column). **Open question: the order.** Proposal for review: Admin above Head above Bursar above Teacher; whether "up to your own" includes equal rank.

## Tests (required)
- A creator cannot assign a role above their own: by the form, by a forged POST, and by editing the role id. Roles at or below succeed.
- A newly created account has `must_change_password = true` and cannot open any page except set-password and sign-out until it is changed.
- The temporary password is not found in any log file, `audit_logs`, session store, queue table or database column in plain text; only a hash is stored. The Activity log entry exists and contains no password.
- Reloading the create response does not show the password again.
- A Head requires a Section; a Section outside the school is refused.
- Username uniqueness across the whole school, case-insensitively; the error suggests two free alternatives.

## Open questions (not decided)
- A **head assigned to more than one Section**: the data allows it (`section_user`), the screen shows one. How does a head with two Sections work with no switcher?
- **Schools with three Sections**: does "the other head" and the Section list generalise as designed?
- Role order, and whether a Head may create Teachers in their own Section (the creator rule implies creators below Admin exist; I have not seen who they are).
- Whether to email the credentials when an email exists (I did not design it: credentials are shown once in person).
