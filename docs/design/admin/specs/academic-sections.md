# Spec: Academic structure, Sections and levels — `screens/academic-sections.html`

States: Normal · Edit a section · First run (no sections) · Error · Saving · No permission.

**Revised 2026-10-23 (owner):** a Level is **never moved between Sections**. Its Section is chosen when the Level is created (Levels tab) and cannot change; a wrong one is deleted and recreated. This screen shows Levels inside their Sections and has **no control to change that**. The "Confirm moving a level" and "Levels not in a section" states are removed.

## Who can open
Proposal: `manage_academics` (Admin). Heads cannot open it (they get the no-permission state).

## Today
`academics` is one page of forms (`academics.levels.store` etc.). `academic_levels` has only a `name`. There is no section, head or head title (NEW).

## Design
- A **card per section**: name, counts, **head title** and **head**, and the levels it contains. **Edit** opens an inline form: section name, head title (required), head (a person with the Principal role). Helper text: the head title is shown as "Principal's comment" on the report card.
- A **Levels** card: every level with its Section shown as plain text (read-only), its class count and student count, and a **Manage levels** link to the Levels tab. The card says a level cannot be moved and how to fix a wrong one (delete it and create it again). No select, no Save levels button.
- **First run:** explains sections and has one form to add the first section. A "Levels come next" card says levels are added on the Levels tab, where the Section is chosen once.
- Not redesigned: Sessions & terms and Subjects (tabs link to the existing screens).

## NEW (D1)
Sections (tenant-owned, `school_id`, `BelongsToTenant`): name, configurable head title. `level.section_id` (each level belongs to exactly one section). The head is a **person assigned to a section** (not a role per section). The school owns this data; nothing about Oakfield is hardcoded.

## Dependent fields
Head title and head fields: only on a section.

## Server rules
Hiding is a convenience, never the boundary: the server validates that a level's Section is never changed after creation (an update that carries a different Section is refused), and that the head is a user of the same school with the Principal role.

## Tests (batch 4)
A head cannot reach this screen or its endpoints; no route or request changes an existing Level's Section (see `academic-setup.md`).

## Decided (owner, 2026-10-11)
- The UI shows **one head per section**, but **section assignments are many-to-many** (user ↔ section), so a vice principal can be added later without changing the model or this screen. Store it as a many-to-many link, never as a single column on the section or the user.
- A staff member's section assignment is edited on the **Admin staff form** (batch 6 adds the field). Until then it is edited here.
- Head title stays **per section** (D1).


## Open (added 2026-10-23, do not decide)
- **Existing schools right after the Section migration:** Levels created before Sections existed have no Section and, with moving removed, this screen can no longer assign them. How they get a Section once (a one-time migration or command with owner-approved mapping, or a one-time screen) is not designed. Codex already records the mapping as a migration concern in `docs/schema.md`; tell me if a screen is wanted.
- **A Level in the wrong Section that already has classes or students cannot be deleted** (delete is blocked), so it cannot be corrected under this rule. Whether to allow an Admin-only exception is the owner's call.
