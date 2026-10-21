# Spec: Arrange your classes from first to last — `screens/classes-arrange.html` (Admin), `screens/head-arrange.html` (head)

States: Best guess (first time) · After moving a class · Saving · Saved · Error (could not save) · School with no departments · No permission (Admin) / No section assigned (head).

## Who can open
Shown **once**, the first time: to the **Admin** (both sections) and to a **head for their own section** (decided 2026-10-18). Reached from the Classes page ("Not arranged yet" notice) and from the blocked state on Promotion. The server refuses a head arranging a section that is not theirs.

## Today
Nothing exists.

## Design
- A pre-filled list of every class in the section, grouped **Section > Level**, with **Move up** and **Move down** on each step. Parallel department classes appear as one step ("SS1 Science and SS1 Arts. Same step. They move together.").
- The list is a **best guess**: section order, then level order (as on Sections and levels), then class name (proposal: natural sort, so "Primary 2" comes before "Primary 10"). A notice says nothing, including Promotion, uses it until the person presses **Save this order**.
- **Save this order** (one primary button) saves every section the person can arrange. Saved state: "Promotion is now available", with **Go to Promotion** and **Back to classes**. After that the Classes page is where order is changed, with Move up and Move down.
- **Moves stay inside the level**; the first and last steps have the matching button disabled. No number is ever shown.

## NEW
A per-section **classes arranged** marker (who, when). Promotion (and anything that reads the position as an order) refuses to run while it is unset. **The system never uses the guess until a person saves it.**

## Dependent fields
Primary School block: Admin only (a head sees only their section). Parallel steps: only when departments exist.

## Tests (trust-sensitive)
Promotion is refused for a section whose arrangement is not saved (server-side); a head cannot save another section's arrangement; saving writes positions exactly as shown and sets the marker; the guess is not written anywhere before Save.

## Open questions (do not decide)
- Whether a school that already has a trusted order (for example only one class per level) may skip this screen.
- Whether adding classes later after the arrangement is saved re-opens it (design: no; the new class is placed through Comes after).
