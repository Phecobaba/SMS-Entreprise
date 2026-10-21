# Spec: Parent attendance — `screens/parent-attendance.html`

States: Normal · Fees unpaid (locked) · No records yet.

## Who can open
Proposal: linked parent, selected child only. `ResultPaymentAccess::authorizeAttendance` already gates by payment.

## Design
Same as the student page: 3 metrics, 30-day strip (each cell carries a letter, never colour alone), day-by-day table, pager. Copy names the child ("Jane's last 30 marked school days"). Locked: neutral card with Pay; metrics show "Locked".

## NEW
Route and a service method returning the child's last 30 records (portal summary gives 5). See `specs/attendance.md` for the Excused-percentage question; it applies here too.


## Update (attendance sessions, confirmed 2026-10-12)
Re-issued as `screens/parent-attendance.html` with the same changes as `specs/attendance.md`: the 30-day strip with a Morning half and an Afternoon half, **Sessions present "53 of 56"**, a Morning and an Afternoon column in the day-by-day list, **Excused, not-held and not-yet-marked sessions excluded** from the percentage, and a holiday (no session held) left out of the school days. Everything stays scoped to the selected child; the locked state stays neutral and shows no data.
