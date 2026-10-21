# Spec: Incoming from Primary — `screens/head-incoming.html`

States: Normal · 3 selected · Review: keep numbers · Review: new numbers · Error (number already used) · Accepting · Accept report · Not continuing: review · Not continuing: report · Nobody waiting · Change a registration number (sheet) · No permission.

## Who can open
**Decided (owner, 2026-10-17):** the **Secondary School head** confirms pupils coming from Primary; the **Admin** can do the same. Shown as the second tab of Promotion for the **Admin** (always) and for the head of a section that follows another section. The Primary head does not see it. Proposal for Codex's RBAC names: the section-scoped promotion permission plus access to the destination section.

## Today
Nothing exists. Pupils reach this list when the Primary head promotes Primary 6 (see `promotion.md`).

## Design
- A list of pupils in **Awaiting admission**: name, registration number, "Primary 6 · waiting N days", **Join class** (default JS1) and **Arm** (default the pupil's Primary arm name if it exists, else the first arm; both editable). Paginated.
- **Accept:** tick pupils, **Review the acceptance**, then **Accept N pupils**, then a report. The review shows the destination table and the **Registration numbers** choice.
- **Registration numbers (decided 2026-10-17):** default **Keep each pupil's current number**. The alternative, **Assign new Secondary School numbers**, shows a table of suggested numbers (from the Secondary format) that the head can edit; the **number must be unique in the school** (clear error when it is not, and the accept button is disabled). The old number stays in the pupil's history and stays searchable; every change is logged (old, new, who, when).
- **Not continuing:** for the selected pupils, or **Mark all remaining as not continuing** (preview, confirm, report). They become **Graduated**, keep their history, and keep read-only access to their own past report cards, paid receipts and payment-gated Attendance for the last 30 recorded school days. There is **no automatic lapse** and no session or term link.
- **No undo.** The review says so; corrections use **Move one student**.
- **Change a registration number** (button, opens a sheet): pupil, current number (read-only), new number. See the rule below.

## Registration number permission (decided 2026-10-17)
A number can be reassigned **at acceptance** into another section, and afterwards by the **Admin** or by the **head of the section the pupil is currently in**. A head can change numbers only for pupils currently in their own section. Proposal (not decided): for a pupil still **Awaiting admission**, only the Secondary head and the Admin may edit, because the Primary head cannot know the Secondary sequence. Proposed as a separate, section-scoped permission (name for Codex to match). The **student record** screen (batch 6) gets the same edit; until then this sheet is the only place.

## NEW
- Status **Awaiting admission** (shared with `promotion.md`).
- Registration number **history** (old numbers kept and searchable). Codex checks where the number is printed or used for lookup (receipts, report cards, login) before enabling edits.
- Section-scoped permission for registration number changes.
- Log entries for accept, not-continuing and number changes.

## Dependent fields
The editable new-number table appears only when **Assign new Secondary School numbers** is chosen. Join class and Arm lists follow the destination section. The Admin sees the same page with a section filter if the school has more than two sections.

## Proposals recorded (not decided)
- On acceptance, if a fee schedule exists for the destination class, the term invoice is created automatically (D7 says new students are invoiced at enrolment). The review states this.
- Whether the Secondary head must tick the Admission requirements before accepting: the design says **no** (these are existing pupils, not candidates).

## Tests (trust-sensitive)
A head cannot accept into or change numbers outside their section (server-side); accepting is idempotent; a duplicate registration number is refused school-wide; results, attendance and invoices stay readable after acceptance and after not continuing; Awaiting admission pupils are excluded from Primary rosters, counts and invoicing; "mark all remaining" affects only pupils still waiting.

## Open questions (do not decide)
- Who may edit the number of a pupil still Awaiting admission (proposal above).
- Whether a pupil accepted by mistake can be returned to Awaiting admission (design: no, use Move one student).
- Awaiting-admission portal access is read-only and limited to past report cards and receipts.
