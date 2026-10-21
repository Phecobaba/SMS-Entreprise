# Spec: Parent profile — `screens/parent-profile.html`

States: Normal · Wrong current password · Password changed.

## Who can open
Any signed-in parent; own details only.

## Today
No profile route. Available data: `users.name`, the sign-in identifier, and linked children (`parent_student`). `parents` holds only `user_id`; **there is no phone or address field**, so none is shown.

## Design
Read-only name and sign-in identifier; "Your children" (name, class, section); "To change your name, your sign-in details or the children linked to you, ask the school office." Optional Change password (current password check, min 8, confirmation). Same behaviour as the student profile.

## NEW
Route(s) as in `specs/profile.md`. Change password is optional for the first release (drop the card, keep the details).

## Decided (owner, 2026-10-10)
No parent phone number. The profile shows name, sign-in identifier and linked children only.
