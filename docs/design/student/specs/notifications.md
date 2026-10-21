# Spec: Notifications — `screens/notifications.html`

States: Normal · All read · None yet.

## Today
`notifications.index`, `notifications.read`, `notifications.read-all`. Rows have `title`, `body`, `read_at`, `created_at`; no type, no link target.
The app currently sends three kinds only: **New assignment**, **Result updated**, **Payment confirmed**. (No exam-open or fee-reminder notifications exist; the ticker sample copy in earlier files that mentioned them was illustrative.)

## Changes
- Restyle. Unread = ochre bar + "New" pill + bold title; read = muted. "Mark read" is a 44px button; "Mark all read" in the heading; success notice after mark-all.
- No per-type icons and no deep links: nothing in the table says what a notification is about. Adding a `url` column would let rows link to the thing (assignments, fees, results), which I recommend but did not assume.
- The ticker uses the same rows (latest 5 unread).
