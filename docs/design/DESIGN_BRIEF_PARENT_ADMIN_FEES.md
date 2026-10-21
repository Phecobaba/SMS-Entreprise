# Design brief: Parent dashboard now, Admin and fees next

Status: recorded 2026-10-10. For the design agent. Companion to `docs/briefs/ADMIN_FOUNDATIONS_BRIEF.md`
(the build decisions). Follow the existing format for every screen: standalone HTML with inline CSS and
inline SVG, `oak-*` classes, the State bar, one spec `.md` per screen, 44px tap targets, the contrast
tokens in `DESIGN_AMENDMENTS.md`, and update `INDEX.md`. Where a screen needs a new column or table,
write "NEW" in the spec. Do not invent fields. If something is not covered, record it as an open
question in the spec instead of deciding it.

## 1. Vocabulary (use these words in every label)

Section (Primary School, Secondary School) > Level > Class > Arm. **Department** (Science, Arts) is an
optional tag on a class, not an arm. Say "Primary School" and "Secondary School" for sections, because
"Primary" is also a level name. Money is always shown in naira (₦45,000), never kobo.

## 2. Parent dashboard (start here)

The student screens already exist; reuse them. What is different for a parent:

1. **Ward switcher.** A parent can have several children. Every page shows whose data it is, and a
   switcher changes the ward. A single-child parent sees no switcher. Show a locked or unpaid state per
   ward, not per parent.
2. **Privacy.** Report cards show only the ward's own position and average. No classmates' names or
   averages. This matches the student decision.
3. **Fees page (parent version).** Per ward: an itemised breakdown (see 3), progress for part-payments,
   receipts, a Pay button per open invoice. A parent can pay for any linked ward. Do not design a "Pay
   all" button; it is not specified.
4. **After paying,** a parent returns to their fees page, not the public homepage (build rule; design the
   return state: confirming, paid, failed).
5. **Part-payment is requested in person**, with the Bursar or Admin. The parent screen only displays the
   resulting invoice and a short line saying so ("To pay in parts, please visit the bursar"). There is no
   request form.
6. Parent-facing screens: dashboard, results list, report card, attendance, assignments, fees, notifications,
   profile. Exams and e-notes are student-facing; do not add them unless told.
7. Include the notification ticker.
8. If the Parent has no linked ward yet, design that empty state.

## 3. Changes to screens already designed

- **Fees (student and parent):** each invoice expands to show its line items and total. A term invoice
  with part-payments shows "₦20,000 of ₦45,000 paid · balance ₦25,000", a list of receipts, and a Pay
  button for the open part-payment only.
- **Report card:** the second comment label follows the section's head title ("Principal's comment" or
  "Head teacher's comment"). It is data, not hardcoded text.
- **Login:** the field is labelled "Email or username".
- **Locked states** stay neutral: no scores, positions or subjects shown behind the lock.

## 4. Screens to design after Parent, in this order

1. **Admin shell and dashboard** with the section switcher (All / Primary School / Secondary School).
   A head (Principal role) has no switcher and sees only their section; show the section name in the
   heading. Analytics cards follow the selected section.
2. **Fee schedule:** fee items, amounts per term and scope (level, class or department), a Required tick
   per item. **Bulk invoice generation** with a preview ("412 students, 389 will be invoiced, 23 already
   have one") and a result report. **Not invoiced** list for the Bursar. Single-student invoice with
   extras. Invoice detail with lines.
3. **Manual payment:** record (bank, teller number, date, amount, optional teller photo), the
   "Awaiting approval" queue, Admin approve or reject. A teller number already used shows a clear error.
4. **Part-payment:** "Create part-payment" on an invoice detail, showing the balance still allocatable,
   amount, due date, note. Voiding an unpaid part-payment.
5. **Payment gateway settings (Admin only):** public key, secret key (write-only, masked after save),
   Test / Live badge, "Test connection", webhook URL to copy with instructions, last changed by, the
   password re-entry step. States: not set up, saved, connection OK, connection failed.
6. **Staff:** add staff (creates the login and the record together; temporary password shown once, with
   print; the person signs in with a username, and email is optional), admin password reset, role assignment limited to roles the creator may
   assign.
7. **Students:** add one; bulk import wizard (upload, validate whole file, preview with row errors,
   confirm, report); registration number automatic or manual; guardian columns. School settings for
   registration number format and prefixes (per section if different).
8. **Academic structure:** sections and levels, departments (an optional list; when none exist, no
   department field appears anywhere), classes with an optional department tag.
9. **Bulk subject assignment:** level, then optional department, then classes (each can be unticked),
   then subjects, with a preview ("adds 12, skips 3"). **Teacher assignments** by teacher and by class.
10. **Promotion:** destination class per student or group, defaulting to the same department.
11. **CBT bulk upload:** CSV template download, validation report, preview, all-or-nothing import; guarded
    delete (blocked once submissions exist, with the reason shown).
12. **Activity log:** tabs by module, filters, pagination, read-only.
13. **Ticker on every role's dashboard** (heads, teachers, Bursar, Admin), including the staff events
    listed in the build brief.
14. Rename "Admission checklist items" to "Admission requirements" with helper text: what a candidate
    must complete before becoming a student.

## 5. New patterns to add to `components.html`

Bulk action bar · import wizard (upload, validate, preview with row errors, confirm, report) · one-time
credentials panel with print · assignment matrix · section switcher · masked secret field · money amount
with breakdown · part-payment progress line · status "Awaiting approval".

## 6. Rules for every new screen

- **Dependent fields:** show a field only when it is relevant (for example a release date only when the
  release type is "Scheduled"). List each dependency in the spec. The server also ignores hidden fields.
- **Bulk actions always preview** what they will add and what they will skip, then confirm, then report.
- **Sensitive fields** (secret key, temporary password) are shown once or masked, never repeated.
- **Hide unused structure:** no department UI when a school has no departments; no section switcher for
  a head.
- **Every state** the State bar needs: normal, empty, error, loading, locked or no permission where it
  applies.
- Say who can open each screen (permission wording) in the spec, as a proposal for Codex to match to its
  RBAC names. Do not invent permission names as final.

## 7. Open questions to record (do not decide)

- Whether heads see the Staff list scoped to their section.
- Whether a Parent can see all siblings' fees in one view (single-ward view is the default).
