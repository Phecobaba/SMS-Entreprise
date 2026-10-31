# Spec: Import students (wizard) — `screens/students-import.html`

Batch 6. Steps: **Upload, Check, Review, Done**. States: Upload · Wrong file · File chosen · Checking · Errors (nothing created) · Ready to confirm · Creating · Report plus credentials · Report: credentials gone · No permission.

## Who can open
Admin with the student-create permission. Not heads (not in this batch).

## Stated current behaviour (Codex: confirm first)
Whether any student import exists today. How classes, parents and fees are linked on a new student (the auto-invoice rule applies to every imported student if a schedule exists).

## Design and rules
1. **Upload** a CSV (UTF-8, tolerate a BOM and Excel's line endings). Offer a **template download**. The page lists the columns: `full_name` (required), `class` (required, exact name), `registration_number` (blank = automatic), `username` (blank = suggested), `email`, `parent_name`, `parent_username`, `parent_email`. Rows sharing a `parent_username` share one parent (brothers and sisters get one family login). **These columns are a proposal**: Codex matches the real admission fields. Row and size limits are NEW: Codex picks them and the page states them.
2. **Check validates the whole file and writes nothing.** Every row is checked: required fields, class exists, registration number format and uniqueness (against the school and inside the file), username rules and uniqueness (school and file), email format, parent consistency.
3. **Review.** *Errors found:* the page says **"Nothing was created"**, lists every problem as **Row, column, message** with a way to download them, and offers only **Upload a corrected file**. There is no partial import: **a file is imported only when every row is valid**.
4. *No errors:* summary **Ready, Skipped, Errors** and a preview of the first rows, then **Create N students**. **Skipped** = a row that matches an existing student exactly (same name, class and registration number), so re-uploading the same file is safe (**proposal**; Codex confirms what "already exists" means).
5. **Confirm runs in one transaction**, re-validates (data may have changed since Check), and is **idempotent**: confirming twice, or two tabs confirming, creates nothing the second time. If anything fails the whole import rolls back and the page shows the Errors state.
6. **Done:** report with **Created, Skipped, Errors** and the skipped rows. Then the **sign-in details**: temporary passwords for each new student and each newly created parent, shown **once** with **Print slips** (a few slips per page) and **Download** (a file made at that moment). After the user leaves, or returns later, the report remains but the credentials do not (state *credentials gone*, with "reset the password from the student").

## Credentials: how they must be handled
Hash only in the database. The plain text must not be stored with the import record, the session, flash data, logs, `audit_logs`, queue payloads or any file left on the server. It exists only in the response to the confirm request (or a one-time, short-lived, encrypted fetch that deletes on first read: Codex proposes). **If the confirm would run as a queued job, the plain text cannot pass through the queue, so run it synchronously or generate on download: open question for Codex.**

## NEW data
A temporary import record (status, counts, file hash, owner, no passwords) so Check and Confirm are linked and idempotent.

## Tests (required)
- A file with **any** invalid row creates **zero** students, users, parents, links and invoices.
- Duplicate registration number or username inside the file, or against the database, is an error naming both rows.
- Confirm is atomic: a forced failure on the last row leaves nothing behind.
- Confirm twice creates nothing the second time.
- Credentials are in no log, audit row, queue payload, session or stored file; the report page after leaving shows none.
- A user without the permission cannot upload, check or confirm, by direct request.

## Open questions
What "Skipped" should cover beyond exact duplicates; row and size limits; whether heads import for their own Section later; sibling matching when `parent_username` is blank.
