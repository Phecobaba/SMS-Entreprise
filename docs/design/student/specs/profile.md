# Spec: Profile — `screens/profile.html`

States: Normal · Wrong current password · Password changed.

## Today
No profile page, no change-password route. Available data: `users.name`, `users.email`, the student's class arm label, `students.admission_date`. There is no photo, phone or address field.

## Changes (new)
- `GET /my-profile`: read-only details; "To change your name or email, ask the school office."
- Change password: **new route** `PUT /my-profile/password` with current password check (`current_password` rule), min 8, confirmation. Throttle it. This is optional for the first release: if you defer it, drop the card, keep the details.
- Sign out stays in the sidebar and the More menu.
