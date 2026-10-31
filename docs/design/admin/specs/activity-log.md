# Spec: Activity log — `screens/activity-log.html`

Batch 6. States: All activity · Tab per module (Students, Staff, Academics, Payments, Sign-in) · Filtered: no results · Nothing yet · No permission.

## Who can open
A user with its **own permission** (name for Codex; proposed `view_activity_log`), the Admin by default. Heads and the Bursar do not see it (nav table). Read-only: nothing on the page edits or deletes a row.

## Stated current behaviour (Codex: confirm first)
`audit_logs` is described as a generic audit table; I have **not seen** which modules write to it today. The Admin sidebar already links `activity-log.html`; confirm whether a page exists. List which modules write, and which Batch 6 events must be added: account created, password reset (staff, student, parent), role or Section changed, registration number changed, students imported (counts), discipline recorded, credentials viewed is **not** logged because it is not stored.

## Design
- **Tabs by module**; filters **Who**, **From**, **To**; newest first; each entry shows time (**Africa/Lagos**), who, a plain sentence, and optional detail. A pager only when there is more than one page.
- A fixed line: **passwords and temporary passwords are never recorded here.**

## Tests
- No entry anywhere contains a password, temporary password, token or payment key. Codex adds a test that scans written audit payloads for the credential fields.
- Without the permission the page returns nothing and no entries are queried.
- Tabs filter by module; dates use Africa/Lagos.

## Open questions
Retention; whether heads get a Section-scoped log later; export.
