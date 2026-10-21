# Spec: Attendance — `screens/attendance.html`

States: Normal · Fees unpaid (locked) · No records yet.

## Today
Only a dashboard section: `AttendanceService::portalSummary` returns `percentage`, `marked_days`, and the 5 most recent records of the last 30. Locked via `ResultPaymentAccess::authorizeAttendance` (dashboard shows "Locked").

## Changes
- New route `GET /my-attendance` showing the last 30 marked days (strip) and a paginated day-by-day list. Add a service method returning the 30 records, not 5. Respect the same payment gate; render the locked card instead of failing.
- Status is never colour-only: each cell and row carries the word (P/L/A/E in the strip, full word in the list).
- **Percentage rule to confirm:** today `percentage = (Present + Late) / all marked records`, so **Excused days lower the percentage**. That looks unintended; I'd exclude Excused from the denominator. The design's text says "Late counts as present" and nothing about Excused until you decide.
- The staff-side "today's percentage hidden until the roll is complete" rule does not apply here (this is per student).


## Update (attendance sessions, confirmed 2026-10-12)
Attendance is taken **twice a day** (morning and afternoon). Re-issued design: `screens/attendance.html`, styles in `shared/oak-attendance.css`.
- **Strip:** the last **30 school days**; each box has a left half (Morning) and a right half (Afternoon), each with a letter and never colour alone. Legend: Present, Late, Absent, **Excused (not counted)**, **Not held (not counted)**, Not marked yet.
- **Metrics:** Attendance %, **Sessions present "53 of 56"**, Sessions absent. The unit is the **session**.
- **Percentage (decided):** (Present + Late) / (Present + Late + Absent) sessions. **Excused sessions are excluded**, which resolves the earlier open question. Sessions **not held** and sessions **not marked yet** are excluded too. A legacy whole-day row (no session) counts as two sessions.
- **Day by day:** Date, **Morning**, **Afternoon** columns. Today's unmarked afternoon shows "Not marked yet".
- **Not held:** a session the school did not hold shows a dashed "Not held" pill. A day on which **neither** session was held (a holiday) is **not a school day** and is not in the 30.
- The pager appears only when there is more than one page. Locked and empty states are unchanged; payment gating is unchanged.
Build notes: `AttendanceService::portalSummary` must return the last 30 school days (not 30 rows) with both sessions. See `docs/design/ATTENDANCE_TWICE_DAILY.md`.
