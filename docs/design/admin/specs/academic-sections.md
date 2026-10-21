# Spec: Academic structure, Sections and levels — `screens/academic-sections.html`

States: Normal · Edit a section · Confirm moving a level · Levels not in a section · First run (no sections) · Error · Saving · No permission.

## Who can open
Proposal: `manage_academics` (Admin). Heads cannot open it (they get the no-permission state).

## Today
`academics` is one page of forms (`academics.levels.store` etc.). `academic_levels` has only a `name`. There is no section, head or head title (NEW).

## Design
- A **card per section**: name, counts, **head title** and **head**, and the levels it contains. **Edit** opens an inline form: section name, head title (required), head (a person with the Principal role). Helper text: the head title is shown as "Principal's comment" on the report card.
- A **Levels** card: each level has a section select (assign a level to a section). Changing a level opens a confirmation sheet that says exactly what moves: its classes and students follow the new section's head; **nothing is deleted**; past results and attendance stay.
- **Levels not in a section** (what existing schools see right after migration): a warning, the affected levels flagged, and Save disabled until every level has a section.
- **First run:** explains sections, one form to add the first section, levels listed but disabled until a section exists.
- Not redesigned: Sessions & terms and Subjects (tabs link to the existing screens).

## NEW (D1)
Sections (tenant-owned, `school_id`, `BelongsToTenant`): name, configurable head title. `level.section_id` (each level belongs to exactly one section). The head is a **person assigned to a section** (not a role per section). The school owns this data; nothing about Oakfield is hardcoded.

## Dependent fields
Head title and head fields: only on a section. The move confirmation: only when the level has classes or students. Section select on a level: required.

## Server rules
Hiding is a convenience, never the boundary: the server validates that every level has exactly one section in the same school, and that the head is a user of the same school with the Principal role.

## Tests (batch 4)
Moving a level changes what the affected heads see immediately and deletes nothing; a head cannot reach this screen or its endpoints.

## Decided (owner, 2026-10-11)
- The UI shows **one head per section**, but **section assignments are many-to-many** (user ↔ section), so a vice principal can be added later without changing the model or this screen. Store it as a many-to-many link, never as a single column on the section or the user.
- A staff member's section assignment is edited on the **Admin staff form** (batch 6 adds the field). Until then it is edited here.
- Head title stays **per section** (D1).
