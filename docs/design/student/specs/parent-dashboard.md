# Spec: Parent dashboard — `screens/parent-dashboard.html`

States: Two children · One child · Fees unpaid (locked) · New child, no data · No child linked.

## Who can open
Proposal: signed-in user with at least one linked child. Data is limited to the selected linked child.

## Today
`dashboard` → `PortalOverview::for($viewer, $requestedStudentId)` (`kind = 'parent'`): `wards`, `selected`, results (last 5 cards, `available`), attendance (percentage + locked flag), payments (last 5 invoices, `outstanding_kobo`), assignments (last 5, `pending`). The `core.dashboard` view already shares a branch with students.

## Differences from the student dashboard
- Ward bar (see `specs/parent-ward.md`); greeting uses the parent's name; role pill "Parent".
- **No exams, e-notes or start-exam banner** (student-facing).
- Metrics: Attendance, Assignments due, Fees outstanding, all for the **selected child**.
- Sections: Report cards, Fees (last 2 invoices), Assignments (3). Ticker included (parent's own unread notifications).
- **Locked state** (child's tuition unpaid): notice names the child; Pay opens the Pay sheet for that child's invoice. Metrics show "Locked"; report-card rows show "Payment required". **No scores, positions or subjects are rendered behind the lock** (the server must not include them in the markup either).
- **No child linked:** the whole page becomes an empty state: "Ask the school office to link your child to your account. Have your child's full name and class ready." There is no self-service linking (linking is an Admin action today: `assignments.parent-wards.store`).

## Dependent fields
Locked notice only when the selected child's invoice rule fails. "Fees due" pill only for a child with an unpaid invoice. Attendance shows "—" until at least one day is marked.

## Decided (owner, 2026-10-10)
- Fees stay a **single-child view**; there is no all-children fees summary on the dashboard.
- The switcher's "Fees due" pill stays (status only, own children only); it appears only in the Fees unpaid state of the design (see `specs/parent-ward.md`).


## Update (attendance sessions, confirmed 2026-10-12)
The Attendance metric is the selected child's **last 30 school days** (95% in the sample) with the small line **"Today: morning present, afternoon not marked yet"**, hidden when locked. Matches `specs/parent-attendance.md`.
