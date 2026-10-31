# Reset Password — Batch 6 follow-up

New/confirm password and same rules as Set your password; expired/used and success states; one-time token. Notify other Admins when Admin password reset.

Design-only HTML is a reference; Backend Agent must confirm API and permissions before Codex implementation.


## Follow-up changes — Batch 6 reissue (2026-10-31)

This reference is additive; no earlier specification at this path was overwritten.

### Confirmed correction

Use shared Set your password rules: at least 8 characters; must not be the username, plus all other rules from canonical shared source. Remove “not the temporary password”.

### Latest implementation alignment

Shared staff Account spec includes different from current password; do not show “not the temporary password”.
