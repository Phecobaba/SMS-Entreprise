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
