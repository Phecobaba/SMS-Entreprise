# Spec: Sign-in rules — `screens/sign-in-rules.html`

Batch 6. States: Normal · Username free · Username taken · Not allowed · No permission. Read-only page for Admins with a **username checker** that saves and reserves nothing. The same rules are shown in short on the login page.

## Rules shown (proposals are marked on screen)
1. **Who signs in with what.** Staff: username, or email if they have one. Students: username (many have no email). Parents: one login for the family, using the parent's own username or email, then a child switcher. Because `@` is not allowed in a username, an identifier containing `@` is an email and anything else is a username: no ambiguity.
2. **Unique across the school**, whatever the role (students, parents, staff, candidates).
3. **Case:** matching ignores case; the value is saved in lower case (**proposal**). Passwords are case sensitive.
4. **Allowed:** 3 to 30 characters, lower-case letters, digits, dot, dash, underscore; starts with a letter or digit; no spaces (**proposal**).
5. **Suggested usernames:** `firstname.surname`, with a number added when taken; editable (**proposal**). The registration number is **not** the username.
6. **Forgot username or password:** ask the school office; an Admin resets (`staff-access.md`, `student-access.md`). No self-service link, because many accounts have no email. This matches `student/specs/login.md` ("No Forgot password link").
7. **Throttle:** unchanged (5 attempts per minute per the login spec).

## Stated current behaviour (Codex: confirm first)
Does login resolve a username today? Is the match case-sensitive? Is `users.username` unique today, per school or globally? Which accounts already have no username (staff created with email only)? What does the throttle key on?

## Design notes
The checker uses the **same validator as the server** (one rule class), so the page and the forms never disagree. Taken names come from the server; the design uses sample values.

## NEW data
Nothing new beyond a shared username validator and, if Codex decides, a case-insensitive unique index.

## Tests
- Username uniqueness is case-insensitive and school-wide across all roles.
- Login accepts `AMARA.EZE` for `amara.eze`; the password stays case sensitive.
- The error on a failed sign-in does not say which of identifier or password was wrong.
- A username containing `@` is refused at creation.

## Open questions
- **What a parent with several children signs in with.** The design shows one login for the family (the parent_student pivot) with the parent's own username or email. Whether a parent can instead use a child's username, or a phone number, is not decided.
- Backfill of usernames for existing accounts that have only an email.
- Whether an account with an email gets a self-service reset later.
