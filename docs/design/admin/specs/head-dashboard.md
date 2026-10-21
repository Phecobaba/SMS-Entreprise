# Spec: Head dashboard — `screens/head-dashboard.html`

States: Secondary head (Principal) · Primary head (Head Teacher) · Nothing waiting · No section assigned (locked) · Error.

## Who can open
Proposal: a user with the Principal role **and an assigned section** (D1: the role says what a head may do, the section says what they may see). Without a section: the locked state.

## Today
The Principal role exists; `view_attendance_alerts` and result approval are the permissions in use. The section does not exist yet (NEW).

## Design
- The **section name is the page heading** ("Secondary School") with the **head title** and greeting underneath ("Principal · Good morning, Mr. Okoye"). On phones the band shows the same. The role pill shows the head title (Principal or Head Teacher), which is configurable data per section.
- **No section switcher.** All figures and lists are the head's own section only.
- Four metrics: Students, Teachers assigned, Attendance today (dash until every class is marked, same rule), Results awaiting approval.
- **Results awaiting your approval** table with Review buttons; **Attendance alerts**; **Admission offers awaiting a reply** count.
- **No section assigned:** locked card, no figures, tells the head to ask the Admin. Nothing is rendered behind it.

## NEW
Section assignment to a person; section on results/alerts/offers (derived through level → section); configurable head title.

## Dependent fields
Head title in the heading and pill comes from the section. Attendance alerts block shows "All classes marked" when none.

## Tests (batch 4, trust-sensitive)
A head cannot see or approve another section's results, alerts, offers or counts, including by editing ids or query strings. A head with no section sees only the locked state.

## Decided (owner, 2026-10-11)
One head is **shown** per section, but **section assignments are many-to-many** (a person can hold several sections and a section can have several people), so a vice principal can be added later without a redesign. The heading and head title come from the section.

## Attendance sessions (confirmed, re-issued 2026-10-12)
"Attendance today" shows Morning and Afternoon with the dash rule, section-scoped; the alerts list names the session ("JSS2 Gold, Morning"); a not-held session shows "Not held" (state "Afternoon not held today"). A head sets the section's alert times and marks sessions not held on **Sessions & times** (`head-attendance-settings.html`). See `specs/attendance-sessions.md`.
