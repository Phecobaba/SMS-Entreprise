# Spec: Attendance sessions — Alerts and Sessions & times
Files: `screens/attendance-alerts.html`, `screens/head-attendance-alerts.html`, `screens/attendance-settings.html`, `screens/head-attendance-settings.html`.
Background and decisions: `docs/design/ATTENDANCE_TWICE_DAILY.md` (defaults A1 to A9 confirmed 2026-10-12, with the owner's changes below).

## States
- **Alerts (Admin):** All sections · Primary School · Secondary School · Confirm: session not held · Saving · Saved (report) · No alerts · Error · No permission.
- **Alerts (head):** Normal · Confirm: session not held · Saving · Saved (report) · No alerts · No section assigned (locked) · Error · No permission.
- **Sessions & times (Admin):** Secondary School · Primary School · Error (times / duplicate) · Confirm: mark not held · Saving · Saved · None marked not held · No permission.
- **Sessions & times (head):** the same, with No section assigned (locked) instead of the section switcher.

## Who can open
- **Admin** (`view_all_sections`): any section, switcher on both screens. **Head:** their own section only, no switcher, section name shown. A head with no section sees the locked state.
- Proposal for the permission: reuse `manage_attendance` (it already guards the lock route) and scope it by section; heads already hold `view_attendance_alerts`. Codex maps to its RBAC names; none is final.

## Today (verified in the code)
`generateMissingAlerts` skips **weekends only** (`isWeekend()`), and the job is scheduled `->weekdays()->dailyAt('16:00')` (logged as **17:00 WAT**; scheduled tasks run in **Africa/Lagos**, decided 2026-10-17). There is **no holiday awareness**: on a weekday holiday it raises an alert for **every** class arm that has students. The alert unique key is `(school, arm, date)`.

## Design
- **Alert times (decided):** Morning and Afternoon due times are set **per section** by that section's head; an Admin can set any section. **Defaults: Afternoon 14:00, Morning 08:00** (the Morning default was bracketed in the request; confirm). Every change is **logged** (actor, section, old and new values) and the screen shows "Last changed by … · date, time". The server rejects a Morning time that is not earlier than the Afternoon time.
- **Alerts:** one per class arm, date **and session**, listed with class, session, date and a Today / Earlier pill. An alert closes when that roll is fully marked. Marking is same-day only, so an earlier day's alert stays until the session is marked not held.
- **Session not held (decided):** a head (own section) or an Admin (any section) can mark a **session** as not held **for a date**, for **the whole section** (half days, holidays). There is **no school calendar**. It: (1) switches off that session's alert and clears the open ones; (2) leaves the session out of attendance percentages and out of the dashboard figure ("Not held"); (3) is logged. Two entry points: **"Session not held?"** on an alert row, and the **Sessions not held** form on Sessions & times (date + Morning / Afternoon / Both).
- **Preview, confirm, report:** both entry points open a confirmation that says what will happen (alerts cleared, left out of percentages, marks already made stay on record but are not counted), then a "Saved" report.
- **Remove** is allowed for **today and future** entries only; past entries are kept as a record (proposal).
- **Teacher marking page** (Teacher screens are out of design scope; spec only): it opens on **Morning before 12:00 and on Afternoon from 12:00 (WAT)**. That only picks the default tab. The teacher can switch tabs, and **the saved session is the tab chosen, never the clock**. A session marked not held shows "Not held, no roll needed" and cannot be marked.

## NEW data (names for Codex to choose and log; update `docs/schema.md`)
- A session on attendance records and alerts (see the attendance document).
- Per-section alert times: section, morning due, afternoon due, updated by, updated at.
- Sessions not held: section, date, session, marked by, created at; unique per section, date and session.
- Audit entries in the existing `audit_logs` for time changes and not-held changes.

## Scheduler (replaces the single job logged as 17:00 WAT and coded as `dailyAt('16:00')`)
Run the alert command **every 5 minutes** (cron already runs `schedule:run` every minute: `oakfield:sync-entrance-cbt-outcomes` is `everyMinute`). For each section and session whose due time has passed today and which is **not marked not held**, ensure alerts for arms not fully marked. Idempotent: unique per arm, date and session. Weekends are still skipped. All times, and the 12:00 default marking tab, use **Africa/Lagos**; stored timestamps stay UTC.

## Dependent fields
Section switcher: Admin only. Remove: only today or future. The session chips on the form: Morning, Afternoon or both. The "already marked" line in the confirmation appears only when marks exist.

## Tests (trust-sensitive)
- A head can read and change **only their own section's** alert times and not-held sessions; an Admin any; the server enforces it on every endpoint. Every change is audit-logged with the actor.
- The server rejects Morning not earlier than Afternoon, and a duplicate not-held entry.
- **Regression for today's gap:** on a date where both sessions are marked not held, the alert job creates **no** alerts for that section; marking a session not held **resolves** its open alerts.
- A not-held session is excluded from every percentage and shows as "Not held"; marks already made stay on record.
- Removal is refused for a past date.
- The alert job honours each section's own due time.

## Open questions (do not decide)
- A **reason or note** on a not-held entry (none is designed; only the actor and time are logged).
- If a session is declared not held **after** some classes marked it: the design keeps those marks and excludes them (proposal).
- Older alerts that can no longer be fixed (marking is same-day only): they stay until the session is marked not held. Should they auto-close instead?
- Whether **removing** a not-held entry for today re-raises an alert on the next run (the design assumes yes).
