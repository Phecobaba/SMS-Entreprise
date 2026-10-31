# Spec: Ticker events on every role (spec only, no screen)

Batch 6. The ticker (the "Latest" strip) is already drawn on the Admin, Head and Bursar shells. This note records which events feed it. **No new screen.**

## Blocked on one owner confirmation
Decision D13 (staff ticker events) assumes a `notifications.url` column so each headline links somewhere. It was **not built**. The owner must confirm adding it before this ships (carried from the handoff). Until then the ticker links to `#`.

## Proposed events (proposals, for the owner to trim)
| Role | Headlines (own scope only) |
|---|---|
| Admin | Offer accepted; Result submitted; Payment awaiting approval; Account created; Import finished |
| Head | Result submitted (own Section); Pupils awaiting admission (Secondary); Attendance alert (own Section) |
| Bursar | Payment awaiting approval; Part-payment recorded; Not-invoiced count changes |
| Teacher | Out of scope for Batch 6 |

## Rules
- Scope: a head sees only their own Section's events (server-side). No headline includes a credential, a salary or a payment key. Headlines name people the viewer may already open.
- The ticker never blocks keyboard users: pause button and reduced-motion behaviour stay as built.

## Tests
A head never receives another Section's headline; no headline text contains a password or temporary password.
