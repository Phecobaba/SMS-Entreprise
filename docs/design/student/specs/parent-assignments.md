# Spec: Parent assignments — `screens/parent-assignments.html`

States: Normal · All caught up · No assignments.

## Who can open
Proposal: linked parent, selected child only.

## Today
`assignments.parent` → `AssignmentService::forParent()` lists **every child's rows in one list** (the row shows the student name).

## Design
Scoped to the selected child (consistent with the ward bar). Filter chips To do / Done / All; expandable rows show the description and set date; no upload, no tick (Mode A: the teacher ticks). Status stays Submitted / Not Submitted / Late; due hints are computed.

## Decided (owner, 2026-10-10)
No all-children assignments view. The list is always the selected child's.
