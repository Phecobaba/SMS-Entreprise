# Spec: Academic structure, Departments — `screens/academic-departments.html`

States: Normal · None defined · Delete blocked (in use) · Error (duplicate) · Saving · No permission.

## Who can open
Proposal: `manage_academics`.

## Design
- Intro card: departments are **optional**; a department is a **tag on a class** (SS2 Science and SS2 Arts are separate classes with their own subjects and results); it is **not an arm**.
- One field to add a department; a list with Rename and Delete. A department that classes use shows its class count and **cannot be deleted** (reason shown). Unused ones can be deleted.
- **None defined:** the page says that while there are no departments **no department field, filter or label appears anywhere** in the system. This is the screen where the first one is created, so it always exists.

## NEW (D2)
Departments (tenant-owned list: name). A nullable department on a class.

## Dependent fields (across the system, D2/D5)
Department tag, filter or field appears in: class lists, Add a class, bulk subject assignment (the Department step), promotion (default destination). Each shows **only when the school has at least one department**. The server also ignores a department value when none exist.

## Decided (owner, 2026-10-11)
A department **cannot be deleted while a class uses it**. The screen shows the reason and the class count; the server refuses the delete.

## Tests (batch 4)
With no departments, no department field is accepted or rendered on the screens above; a duplicate name (per school, case-insensitive) is rejected.
