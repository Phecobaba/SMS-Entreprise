# Spec: Head Staff list and detail — `screens/head-staff.html`, `screens/head-staff-detail.html`

Batch 6. States, list: Normal · No results · No staff in the Section · Head with no Section · No permission. States, detail: Normal · Nothing recorded · Record: sheet · Record: missing text · Recorded · Outside the Section · Head with no Section · No permission.

## Who can open
A head (Principal) with the head Staff permission (name for Codex; proposed `view_section_staff`, `record_section_discipline`). Decided 2026-10-11: heads **view staff**, **view and record discipline**, and **never see salary, exit records or documents**, and **cannot edit employment details**.

## Stated current behaviour (Codex: confirm first)
I have not seen today's Staff HR pages. Confirm: how `staff_disciplinary_log` (type Query / Warning / Commendation, date, description, issued_by, resolution) is written and shown to Admins today; that `staff_sensitive_view_audit` logs **every** salary or discipline view; and how a staff member is tied to a Section (`section_user`, or the Section of the classes they teach).

## Who is in the list
Staff of **the head's own Section**, **excluding**: Admin accounts, any user who can view all sections (`view_all_sections`, so the Bursar and others), **the other head**, and the head themself. The definition of "in a Section" is an **open question** (see below); the design assumes Section assignment.

## Design
- **List:** Staff (name, role), Teaching (subjects and classes **in this Section only**), Discipline (a count summary), View. **No salary, bank, phone or documents column.** A notice says what Heads never see.
- **Detail:** profile (name, role, Section only: **Codex lists exactly which existing fields a head may see; start from this minimum**), Teaching in this Section, and **Discipline**: entries newest first with type as a word (Query, Warning, Commendation) plus date, issued by, description, resolution. **Record discipline** opens a sheet: type, date (default today), what happened (required), resolution (optional). The card says opening it is logged.
- **Entries are append-only for heads:** no edit or delete; a correction is a new entry (**proposal**).
- **Head with no Section:** the locked state; no data, no queries for staff.
- **Staff outside the Section:** the detail shows "This person isn't in your Section" and **no data**. The server answers as if the record does not exist.

## Enforcement (server-side, not hidden UI)
Queries are scoped to the head's Section; staff payloads are built from an allow-list of fields so salary, exit and document data **cannot** be serialised to a head even by a crafted request, Livewire call or API route. `issued_by` is always the signed-in head. The date cannot be later than today (Africa/Lagos).

## Tests (required)
- A head sees only staff in their own Section; the list never contains Admins, `view_all_sections` holders or the other head.
- A head can never read salary, exit records or documents by any route (page, JSON, Livewire, export).
- A head cannot open, view discipline for, or record discipline for staff outside their Section **by editing ids**.
- A head with no Section sees the locked state and receives no staff data.
- Viewing the discipline card writes a `staff_sensitive_view_audit` row; recording writes a log row with `issued_by` = the head.
- A head cannot edit or delete an entry, and cannot edit employment details.

## Open questions
- A **head assigned to more than one Section**: what does the list show with no switcher?
- A **teacher who works in both Sections** (or has no Section): which head sees them?
- **Three-section schools:** "the other head" becomes "the other heads".
- Which profile fields a head may see; whether a head may see entries made by the Admin; how a wrong entry is corrected.
