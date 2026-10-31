# Spec: Login (all roles) — `screens/login.html`

States: Normal · Wrong details · Too many attempts · Signing in.

## Today
`login` (guest) → `core/login.blade.php`, inside `_shell-start` + `_public-header`. `POST login.store` is throttled `5,1`.

## Changes
- Standalone guest layout (no sidebar, tab bar or ticker). Desktop: the **full real logo** on a teal panel (its background is exactly `#2E4F56`, so it blends), form on the right. Phone: teal band with the emblem crop and school name, then the form.
- Use the school-profile logo source (`CurrentSchoolProfile`) so an uploaded logo replaces the default; the panel colour must stay `--color-brand-teal`.
- Show/hide password button (44px, `aria-pressed`). "Keep me signed in on this device" replaces "Remember me" (same field).
- Error and throttle messages come from the server; the throttle notice shows the real seconds from the `Retry-After`/validation message and disables the button until then.
- **No "Forgot password" link:** there is no reset route. The copy tells people to ask the school office. If you add resets later, this is where the link goes.
- Shared by admission candidates and every staff role; nothing here is student-specific.


## Update (batch 3): "Email or username"
The identifier field is labelled **Email or username** and is a text input (`autocomplete="username"`, no email keyboard). The server accepts either. NEW: a `username` on users, as defined by the Admin foundations brief (staff sign in with a username; email is optional). The throttle (`5,1`) and error copy are unchanged; the error message must not say which of the two was wrong.

## Update (Batch 6): usernames, forgot credentials, first sign-in
Three text lines changed in `screens/login.html` (shown in the Batch 6 change list): the error now reads "Check your email or username and your password, and try again"; a hint under the identifier field ("Students and parents: use the username on your slip. It does not matter if you type capital letters."); the forgot line reads "Forgot your username or password? Ask the school office. An Admin will give you a new temporary password." Still **no Forgot password link**. After a successful sign-in, an account with `must_change_password` goes to `set-password.html` (`specs/set-password.md`) before anything else. Username rules: `admin/specs/sign-in-rules.md`. Codex confirms the stated current behaviour listed there (does username login work today; is matching case-insensitive).


## Follow-up changes — Batch 6 reissue (2026-10-31)

# Login — Batch 6 follow-up

Preserve current copy: Sign in; Use the email or username and password the school gave you; students and parents case-insensitive slip username. Add Forgot password? link. For accounts without email: Ask the school office.

Design-only HTML is a reference; Backend Agent must confirm API and permissions before Codex implementation.

