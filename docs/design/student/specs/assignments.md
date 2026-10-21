# Spec: Assignments — `screens/assignments.html`

States: Normal · All caught up · No assignments.

## Today
`assignments.mine` → `assignments/mine.blade.php`: flat paginated list of `assignment_submissions` with the status pill.

## Changes
- Filter chips To do / Done / All (query string `?show=todo|done|all`, default To do; server-side so pagination works).
- Each row expands in place (`<details>`) to show the description and set date. **No detail page, no upload:** Mode A is teacher-ticked only; the intro line says so.
- Status stays exactly Submitted / Not Submitted / Late. The due hint ("Due in 3 days", "Overdue by 2 days") is computed from `due_date` and does not change the stored status.
- Eager-load `assignment` with its subject label (no N+1).
