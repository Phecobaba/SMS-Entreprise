# Spec: Parent notifications — `screens/parent-notifications.html`

States: Normal · All read · None yet.

## Who can open
Any signed-in user; own rows only (existing `notifications.*` routes).

## Today
A parent receives notifications about **all** their children (`userIdsForStudents`): New assignment, Result updated, Payment confirmed. The row has only `title`, `body`, `read_at`, `created_at`, and the shared body does not say which child it concerns.

## Decided (owner, 2026-10-10)
**Notifications name the child in the text**: "For Jane: Algebra worksheet 4 is due 12 Oct 2026." There is no child-tag pill and no separate "as data is today" state.

## Design
Same list as students (unread = ochre bar + "New" pill; Mark read 44px; Mark all read). The child's first name leads the body text. The ticker shows the same text.

## Build note (NEW behaviour, no schema change)
Assignment, result and payment notifications are sent to students and parents with **one shared body today**. The "For {first name}:" prefix must be added **only to the parent's copy**, otherwise a student would read "For Jane:" about themselves. Send two bodies (student copy unchanged, parent copy prefixed) in each sender; verify each sender in `AssignmentService`, `ResultWorkflow` and `PaymentLedger`. A parent with several children gets one notification per child, each prefixed with that child's name.

## Open questions
- Deep links (`url`) so a row opens its page: still undecided (see the student notifications spec).
