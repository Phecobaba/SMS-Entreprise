# Spec: Admin shell and navigation by role (all files in `screens/`)

## Who sees what
Navigation is **permission-driven** (`AppNavigation` checks permissions, never role names). The sets below are what the design shows; the build shows an item only when its page exists **and** the user holds the permission. Proposed permission names are for Codex to match to its RBAC; none is final.

| Item | Admin | Head (Principal) | Bursar | Page status |
|---|---|---|---|---|
| Dashboard | yes | yes | yes | Batch 4 |
| Academics (structure) | `manage_academics`, any section | **Academics group, own section**: Classes, Bulk subjects, Teacher assignments, Promotion (the Secondary head's Promotion page has the Incoming tab) | no | Batch 4 |
| Results | yes | approve (`approve_results`) | no | existing |
| CBT, Attendance, Assignments, E-Notes | yes | Attendance (alerts) | no | existing |
| Admissions | yes | no | no | existing |
| Students | yes | no | no | Batch 6 |
| Payments | `manage_payments` | no | yes | existing |
| Fee schedule, Not invoiced | `manage_fee_schedule` | no | yes | Batch 5 |
| Staff HR (Admin) / Staff (head) | yes | **section-scoped Staff page** | no | existing / **Batch 6 (head)** |
| School profile | yes | no | no | existing |
| Payment gateway | `manage_payment_gateway` (Admin only) | no | no | Batch 5 |
| Activity log | own permission | no | no | Batch 6 |

## Layout
- **Admin:** 170px sidebar grouped **Teaching / Daily ops / Administration** (new `oak-nav-group` labels, `data-dense` on the shell). Bursar: the existing 150px sidebar, no groups. **Head (decided 2026-10-18): the 150px sidebar with an Academics group**: Dashboard, then **Academics** (Classes, Bulk subjects, Teacher assignments, Promotion), then **Teaching** (Results, Attendance), then **People** (Staff). Long labels wrap to two lines.
- **The nav area scrolls on its own; the brand and Sign out stay pinned.** The nav list is a flex child with `min-height: 0; overflow-y: auto`, with subtle scroll shadows. On short laptop screens (mouse pointers only, height ≤ 800px) rows tighten from 40px to 36px so more of the nav fits. Verified at 1366×650, 1366×720 and 1366×768: brand and Sign out always visible, the last item always reachable (also by keyboard focus). At 1366×768 the Admin nav fits without scrolling; at 650 and 720 it scrolls inside the nav. Head and Bursar fit at all three. **The same rule fixes the shared shell** (`.oak-sidebar-links` has no height limit today), so apply it in the shared CSS, not only for Admin.
- **Phone tab bar** (explicit order, not "first four of the list"): Admin Home, Academics, Results, Payments + More (everything else). Head Home, Results, Academics (opens Classes), Attendance + More (Bulk subjects, Teacher assignments, Promotion, Staff, Notifications). Bursar Home, Payments, Fee schedule + More (Not invoiced, Notifications).
- **More is always present for staff** so Sign out stays reachable on a phone.
- Staff get an **Account** page for changing their password (batch 6); until it exists there is no "My profile".
- The bell and the ticker are the shared components. The ticker sits under the band on phones and at the top of the content on desktop.

## Section scope
- Admin (`view_all_sections`): All / Primary School / Secondary School switcher on dashboards and class lists. Real build: links with `?section=`, remembered for the session, validated server-side. **Single-section schools get no switcher** (decided).
- Head: no switcher. The section name is in the heading and the phone band. Every list is filtered to their section **server-side**.
- **Bursar: whole school, no switcher** (decided).

## Decided (owner, 2026-10-11)
- **Heads get a Staff page, scoped to their section (design it in batch 6).** They **view staff**, and **view and record discipline** (query, warning, commendation). They **never see salary, exit records or documents**, and **cannot edit employment details**. Their list **excludes Admin accounts, users holding `view_all_sections`, and the other head**. A staff member is "in a section" through the many-to-many section assignment.
- Section assignments are **many-to-many** (a vice principal can be added later); one head is shown per section.
- Bursar: whole school, no switcher. Single-section schools: no switcher. **Heads write within their own section (decided 2026-10-18):** they create, edit, move and delete classes, run bulk subject assignment and teacher assignments, and promote (2026-10-17); the Admin does all of this in any section. The Secondary head's Promotion page has a second tab, **Incoming from Primary**, with the waiting count. One teacher per class subject in the UI.
