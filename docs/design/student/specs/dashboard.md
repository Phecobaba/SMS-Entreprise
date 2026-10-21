# Spec: Student dashboard — `screens/dashboard.html`

States shown by the design toolbar: **Normal**, **Fees unpaid** (locked), **New / empty**.

## What exists today (from the source files)
- Route `dashboard` → `DashboardController` → `DashboardOverview` → `PortalOverview::for()` (`kind = 'student'`).
- `resources/views/core/dashboard.blade.php`, branch `in_array($overview['kind'], ['parent','student'])`: three metrics
  (Attendance, Assignments pending, Outstanding fees), three anchor actions, then sections for results, attendance, payments, assignments.
- Locked flags already exist: `attendance_locked`, and each result card's `available` (false → "Payment required").

## What the design changes
1. **Heading**: greeting + class and term, bell with unread count, role pill. Phone shows the greeting in the teal band instead.
2. **Notification ticker** above the page (see `DESIGN_AMENDMENTS.md` §4). Needs `InAppNotifications::latestUnread(User, 5)` (new, tenant-scoped, one query).
3. **Locked notice** (only when results/attendance are locked): "Pay ₦X" posts to `payments.mine.begin` for the latest unpaid tuition invoice.
   Text says why it is locked. Use the existing `ResultPaymentAccess` result; do not recompute the rule in the view.
4. **Open-exam banner** (only when a Published exam for the student's class is open and not submitted): "Start exam" opens the
   start sheet (see `specs/exams.md`); it never POSTs on a single tap because `cbt.start` starts the clock. If an attempt is In Progress, the button reads "Resume exam".
5. **Metrics**: Attendance (— until the whole roll is marked; "Locked" with a lock when payment-locked), Assignments due, Fees outstanding (green ₦0 when paid up).
   There is **no "average" metric**: `PortalOverview` has no average and the design does not invent one.
6. **Sections**: My exams (next 4), Report cards, Assignments (3), New e-notes (2). The old "Recent attendance" and "Payments" blocks move to their own pages.
7. Phone: tables use `.oak-stack` rows; two-column sections stack.

## Data needed
| Block | Source | New work |
|---|---|---|
| Metrics, report cards, assignments, fees | `PortalOverview` | none |
| Open exam, My exams | `ExamWorkflow::availableFor` plus the student's own `exam_submissions` (eager-load once) | small query method returning status per exam |
| New e-notes | `ENote` rows for the student's `class_id` | **new** student-scoped listing (see e-notes spec when designed) |
| Ticker, bell count | `InAppNotifications` | `latestUnread()`; `unreadCount()` already exists |

## Server-side rules
Payment gating stays server-side on every fetch (`ARCHITECTURE.md` §6). The view only renders what the service returns.

## Empty / error
Every block has an explicit empty state (shown in the design). A failed Pay redirect shows a `oak-notice-danger` with the reason.


## Update (attendance sessions, confirmed 2026-10-12)
The Attendance metric is the **last 30 school days** (95% in the sample, matching the Attendance page) with a small line **"Today: morning present, afternoon not marked yet"**. The line shows only when today has a marked session and is hidden when the metric is locked.
