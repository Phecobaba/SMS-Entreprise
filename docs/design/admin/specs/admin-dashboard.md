# Spec: Admin dashboard — `screens/admin-dashboard.html`

States: All sections · Primary School · Secondary School · New school (empty) · Error · Loading.

## Who can open
Proposal: `view_dashboard` plus `view_all_sections` for the switcher (without it the user is treated as a head with an assigned section, or sees the not-assigned state).

## Today
`DashboardOverview` (Admin branch): four metrics (students, assigned teachers, today's attendance, unpaid tuition invoices), three actions (Admissions, Staff HR, invoices), and submitted/rejected result approvals. Every figure is tenant-scoped; today's attendance shows an em dash until the whole relevant roster is marked.

## Design
- Segmented **section switcher**; every card, count and list follows the selection. The page also says which scope it is showing.
- **Four metrics** (Students, Teachers assigned, Attendance today, Unpaid tuition invoices) in a 4-up grid (2×2 on phones).
- **Teachers assigned = distinct teachers with at least one assignment in the selected scope.** A teacher who works in both sections counts **once** in All (sample: Primary 12, Secondary 16, All 27). It is the one figure that is not additive across sections.
- **Attendance rule kept:** the figure is a dash until **every** class in the selection is marked ("Secondary: 3 of 9 classes marked"). So "All sections" shows a dash if any section is incomplete, while Primary alone can show 96%.
- **Needs attention:** Results awaiting approval, Classes with attendance not marked, Admission offers awaiting a reply. Each is a link with a count.
- **Quick actions:** Admissions, Staff HR, Invoices (as today).
- **Results awaiting approval** table (5 newest). The **Section tag** shows only in "All sections". Review opens the existing approval screen.
- Empty and error states are explicit; Loading shows skeletons.

## Section scoping (D1)
Scoped by section: result approval queue, attendance alerts, analytics cards, admission offers, class and student counts. Admission offers need an **applied level** on the candidate to know their section: verify first item 11 of the foundations brief; if missing, do not show the offers row and report it.

## NEW
`view_all_sections`; a way to resolve "section of a result / class / alert / offer" (level → section). No new tables are invented here.

## Dependent fields
Switcher: only with 2+ sections. Section tag on rows: only in "All sections". Offers row: only when the candidate→level link exists.

## Tests (batch 4)
- **Additive figures** (Students, Results awaiting approval, attendance alerts, admission offers, unpaid tuition invoices): the All figure equals the sum of the sections.
- **Teachers assigned** counts distinct teachers in scope: a teacher assigned in both sections counts once in All, so All can be less than the sum.
- Each figure and list changes with the section; a user without `view_all_sections` cannot request another section (`?section=` is ignored or rejected).

## Decided (owner, 2026-10-11)
- The dashboard lists **submitted results only**. Rejected results are the teacher's to fix and do not appear here.
- Single-section schools get **no switcher**.

## Attendance sessions (confirmed, re-issued 2026-10-12)
- **Attendance today** shows **Morning and Afternoon separately**, each with the dash rule: a session shows a dash until every class in the selection has that session marked. A session marked **not held** shows "Not held" (state "Primary: afternoon not held"), with a notice.
- **Rolls not marked by their deadline today** counts class-and-session alerts raised today (additive across sections). **Alert times and sessions not held** links to `attendance-settings.html`.
- Both cards are built **behind `AttendanceService`**; see `specs/attendance-sessions.md` and `docs/design/ATTENDANCE_TWICE_DAILY.md`.
- The staff notification events behind the ticker (offer accepted) are NEW per D13 and belong to batch 6.
