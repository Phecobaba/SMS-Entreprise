# Spec: Promotion — `screens/promotion.html` (Admin), `screens/head-promotion.html` (heads), `screens/head-incoming.html` (Incoming from Primary)

Re-issued 2026-10-18. Both promotion screens are now generated from one design, so Admin and head behave identically apart from the section filter.

States, Admin: Normal · 3 selected (bulk) · **Choose a class (two classes at the next step)** · School with no departments · Error (no destination) · Review the move · Moving · Result report · Empty class · Final class: Awaiting admission · Final: review · Final: report · **Final class: Graduated** · Graduate: review · Graduate: report · Move one student · **Arrange your classes first (blocked)** · No permission.
States, head: the same, plus **Secondary head view** (with the Incoming tab), and **No section assigned (locked)** instead of No permission.

## Who can open
**Decided (owner, 2026-10-17):** heads promote **within their own section**; the **Admin** promotes in any section (the Admin screen has a **Section** filter; the head has none). The **server** refuses any source or destination class outside a head's section. Codex names the section-scoped promotion permission and logs it. A head with no section sees the locked state.

## Today
No promotion feature exists. A student's placement is `students.class_arm_id`; results, report cards and attendance already snapshot the class arm per record, so past records keep their class.

## Blocked until the classes are arranged
**Decided (owner, 2026-10-18):** Promotion follows the **position** of classes (see `academic-classes.md`). Until the arrangement is saved (see `classes-arrange.md`), Promotion shows **"Arrange your classes first"** with the button **Arrange your classes** and nothing else. The system never uses the guess until a person saves it. The server refuses the action as well.

## Design (D2)
- Choose the class to move students **from** (Admin: choose the Section first), and optionally an arm. Each student gets a **destination class and arm**, pre-filled with the suggestion; everything is editable per student, and tick several to use the bulk bar. A student who repeats stays in the same class (highlighted).
- **Suggestion rule:** the class at the **next step**; when departments exist, the class at the next step **with the same department tag**; the same arm name if the destination has it, otherwise the first arm.
- **Choose a class (decided 2026-10-18):** when **more than one class sits at the next step and the source has no department** (for example JS3 to SS1 Science or SS1 Arts), **no destination is pre-selected**; each row says **Choose a class**; **Move is disabled until every student has one**. The bulk bar lets the person choose once for several students.
- **Review the move** summarises "Will move N · Will stay M", lists each destination, then **Move N students**, then a **result report**.
- **No undo (decided).** Every review says "This can't be undone." Corrections use **Move one student** (a separate sheet, no preview step).
- Paginated (pager only when more than one page). Past results and attendance stay with the term they were recorded in.

## The last class of a section (decided 2026-10-17)
- **Last class of the school (SS3):** students move to **Graduated** (history kept; they leave active lists and counts; read-only access to their own past report cards, paid receipts and payment-gated Attendance for the last 30 recorded school days). The screen has the **Graduated** states (Admin and the Secondary head).
- **Last class of a section that is followed by another section (Primary 6):** pupils move to **Awaiting admission**, leaving that section's class lists, attendance rosters, counts and automatic invoicing **immediately**; results, attendance and invoices stay in their records. The Secondary head (or the Admin) then **accepts** each pupil into JS1 or marks them **not continuing** (Graduated): see `incoming-admissions.md`. **Manual only, no automatic lapse, no session or term link.** The Primary head does not mark pupils as leaving.
- "Last class of a section followed by another section" is derived from **position and section order**. Codex confirms how both are stored.
- Because a Primary head cannot write into Secondary, **the Primary head cannot undo** Awaiting admission. The review says "ask the Secondary School head or the Admin" (recorded as an open question).

## NEW
- The **position** on classes and the **classes arranged** marker (`academic-classes.md`, `classes-arrange.md`).
- A pupil status **Awaiting admission** and a **Graduated** status (match to the existing status field; verify what students have today). Both are excluded from active class lists, class counts, attendance rosters, automatic invoicing and the dashboard Students figure.
- A section-scoped promotion permission (Codex names it).
- Log entries for every move, acceptance, not-continuing action and number change (actor, from, to, time).

## Dependent fields
Section filter: Admin only. Department part of the suggestion: only when departments exist. Destination arm list follows the destination class. In a final class the arm column is not applicable. The Incoming tab: Admin always; a head only for a section that follows another section.

## Tests (trust-sensitive)
A head cannot promote from or to a class outside their section, even by editing ids; the Admin can. Promotion is refused until the arrangement is saved. Past results and attendance rows are unchanged by any move. Re-running the same move changes nothing. Promoting Primary 6 sends pupils to Awaiting admission and removes them from Primary rosters, counts and invoicing while results, attendance and invoices stay readable. Graduating keeps results, attendance and invoices readable and removes the student from active counts. With two classes at the next step and no source department, no destination is pre-selected and the move is refused until each student has one (server-side). The single-student move validates class and arm the same way.

## Decided (owner)
Graduated status keeping history, no undo, single-student move (2026-10-11). One school-wide class sequence; default arm = same arm name else first arm; destination class, arm and position editable; no automatic link to a session or term; graduated students keep read-only access to their own past report cards, paid receipts and payment-gated Attendance for the last 30 recorded school days; heads promote within their own section; Primary 6 goes to Awaiting admission and remains limited to report cards and receipts; manual only (2026-10-17, portal access amended 2026-10-19). Nobody types an order number; promotion blocked until arranged; Choose a class state (2026-10-18).

## Open questions (do not decide)
- Three-section schools and sections not in a simple sequence.
- Who corrects a pupil sent to Awaiting admission by mistake (a Primary head cannot; proposal: Secondary head or Admin).
- Awaiting-admission portal access is read-only and limited to past report cards and receipts.
- Suggestion rule for combinations the designs do not cover (a source with a department and a next step with no class of that department).
