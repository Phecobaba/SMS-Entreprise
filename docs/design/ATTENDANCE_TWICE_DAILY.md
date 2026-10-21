# Attendance twice a day: confirmed decisions and design impact

Status: **confirmed 2026-10-12** (defaults A1 to A9, with the owner's changes below). Designs re-issued; Codex builds after reading this file.
Where this file and an older doc conflict on attendance, this file wins (update the older doc in the same session).

## 1. In plain words
Each school day has two rolls, **Morning** and **Afternoon**. Each roll marks every student in a class arm Present, Late, Absent or Excused. Marking, locking, alerts, the dashboard figure and the portal percentage are **per day and session**.

## 2. What exists today (verified in the code)
- `attendance_records`: unique `(school_id, student_id, date)`; `markDay` takes the whole arm for **today only**; `lockDay` locks the arm and date.
- `attendance_alerts`: unique `(school_id, class_arm_id, date)`. `generateMissingAlerts` **skips weekends only** (`isWeekend()`); the job is scheduled `->weekdays()->dailyAt('16:00')` (the Decisions Log records this deadline as **17:00 WAT**; the owner decided 2026-10-17 that the scheduler runs in **Africa/Lagos**, so the code and the log must be made to agree). **There is no holiday awareness: on a weekday public holiday it raises an alert for every class arm that has students.**
- `dashboardToday`: a percentage only when every student in scope is marked, otherwise a dash. `portalSummary`: the last 30 **records**; percentage (Present + Late) / marked, so Excused currently counted against students.

## 3. Decisions
| # | Decision |
|---|---|
| A1 | A **session** (Morning / Afternoon) on attendance records and alerts. Unique `(school, student, date, session)`; alerts `(school, arm, date, session)`. |
| A2 | **History untouched:** existing rows have no session (null) and count as a whole day = both sessions. New rows always carry a session. |
| A3 | **The same form teacher marks both rolls.** Two tabs. **The marking page opens on Morning before 12:00 and on Afternoon from 12:00 (WAT). That only picks the default tab: the teacher can switch, and the saved session is the tab chosen, never the clock.** The afternoon roll shows each student's morning status. |
| A4 | Same-day edit window and lock stay, **per session**. |
| A5 | **Morning and Afternoon alert times are set per section by that section's head; an Admin can set any section. Changes are logged.** **Defaults: Afternoon 14:00, Morning 08:00** (the Morning default was bracketed in the request; confirm). Alerts are per session. The job runs **every 5 minutes** and honours each section's times. |
| A6 | Percentage = (Present + Late) / (Present + Late + Absent) **sessions** over the last 30 school days. **Excused sessions are excluded** (closes the open question from student batch 2). Sessions **not held** and sessions **not yet marked** are excluded too. Morning and Afternoon weigh equally; a legacy whole-day row counts as two sessions. |
| A7 | "Attendance today" shows **Morning and Afternoon separately**, each with the dash rule; a not-held session shows "Not held". |
| A8 | Payment gating unchanged: attendance is open or locked as a whole. |
| A9 | Weekends skipped as today. **A head or Admin can mark a session "not held" for a date, for their section** (half days, holidays). It suppresses that session's alert, clears the open ones, and excludes the session from percentages. **No school calendar.** Remove is allowed for today and future entries only (proposal). Logged. |
| n/a | **No "mark the whole day" shortcut** for now. |

## 4. Designs (all re-issued and marked ready in the INDEX files)
| Screen | File | What changed |
|---|---|---|
| Student and parent Attendance | `student/screens/attendance.html`, `parent-attendance.html` | 30-day strip with a Morning half and an Afternoon half; "Sessions present 53 of 56"; Morning and Afternoon columns; not-held and excused legend; holidays are not school days. |
| Student and parent dashboards | `student/screens/dashboard.html`, `parent-dashboard.html` | Attendance 95% (matches the page) and "Today: morning present, afternoon not marked yet". |
| Admin and Head dashboards | `admin/screens/admin-dashboard.html`, `head-dashboard.html` | Attendance today as Morning and Afternoon; "Rolls not marked by their deadline today"; "Afternoon not held" state; links to Sessions & times. |
| **NEW** Attendance alerts (Admin, head) | `admin/screens/attendance-alerts.html`, `head-attendance-alerts.html` | Alerts by class, session and date; **"Session not held?"** with a confirmation and a report. |
| **NEW** Sessions & times (Admin, head) | `admin/screens/attendance-settings.html`, `head-attendance-settings.html` | Per-section alert times (with "last changed by"); **mark a session not held** (date, Morning / Afternoon / Both) with a confirmation; the list of entries. |
| Teacher marking page | spec only (`admin/specs/attendance-sessions.md`) | Two tabs, default by clock, saved session = chosen tab. Teacher screens are out of design scope. |

## 5. NEW data (names for Codex to choose and log; update `docs/schema.md`)
A session on records and alerts; per-section alert times (section, morning due, afternoon due, updated by, updated at); sessions not held (section, date, session, marked by, created at; unique per section, date, session); audit entries in the existing `audit_logs`.

## 6. Build order
1. Record the decision (`PROGRESS.md`, `docs/schema.md`, the attendance-locking line in `ARCHITECTURE.md` section 8).
2. Migration (nullable session, new unique keys, the two new tables). No backfill.
3. `AttendanceService` (session everywhere; `dashboardToday` two figures; `portalSummary` last 30 school days; exclusions in A6).
4. Alert job every 5 minutes, per section and session, honouring due times and "not held".
5. Screens: alerts and Sessions & times, then the dashboards and the student and parent Attendance pages.

## 7. Tests required (trust-sensitive)
One record per student, date and session · saved session = tab posted, not the clock · lock per session · legacy rows count as two sessions · Excused, not-held and unmarked sessions excluded from the percentage · a head reads and changes only their own section's times and not-held sessions (Admin any), always audit-logged · Morning earlier than Afternoon (server-side) · **no alerts for a not-held session, and its open alerts resolve (the holiday regression)** · a past not-held entry cannot be removed · `portalSummary` returns 30 school days · payment gating still hides attendance.

## 8. Verify first (Codex records Confirmed or Different, file and line)
Every query on `attendance_records` and `attendance_alerts` (dashboards, `PortalOverview`, alerts, any PDF or report); whether the **report card** shows attendance counts anywhere; how `class_arm_id` is used when a student moves arms mid-day.

## 9. Open questions (do not decide)
- A **reason note** on "not held" (only the actor and time are logged today).
- Marks already made before a session is declared not held: the design keeps them and excludes them (proposal).
- Older alerts that can no longer be fixed (marking is same-day only): they stay until the session is marked not held; should they auto-close?
- Whether removing today's not-held entry re-raises an alert on the next run (assumed yes).
- Confirm the Morning default of 08:00.
