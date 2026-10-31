# Spec: Registration numbers (school settings) — `screens/registration-numbers.html`

Batch 6. States: Normal (prefix per Section) · Editing: year added · One prefix for all · Prefix not allowed · Saved · No permission.

## Who can open
Admin (proposed `manage_school_settings`; Codex matches). 

## Stated current behaviour (Codex: confirm first)
There is **no registration number column on students today** (per `docs/schema.md`, as I read it), so numbers, history and these settings are NEW. The older designs use sample numbers such as `PRI-0412` and `REG-0412`; the samples were inconsistent and are not data.

## Design
- **School prefix** (2 to 6 letters or numbers), **Number length** (zero-padded; 3 to 6), **Include the year** (optional), and **Use a different prefix for each Section** with one prefix per Section (blank falls back to the school prefix).
- A live **Examples** box shows what a number looks like. Format: `PREFIX-[YEAR-]NUMBER` with a dash (**proposal**).
- **Manual override** is not set here: it is a permission used on Add student and Student sign-in and number. A typed number must be unique across the school.
- **The number never encodes the class or level.** Changing the format changes new numbers only.
- **Where the number is shown or searched** table: the places I have seen in the designs, and a last row for places Codex must check (receipts, invoices, report cards, sign-in slips). **This is the list Codex uses to plan Promotion renumbering (5c).**

## Promotion (5c): the decision this raises
With per-Section prefixes, a pupil who moves Primary to Secondary changes Section, so a "never renumber" rule and a "prefix follows the Section" rule collide. The screen says so and **does not renumber anyone**. The earlier decision stands: keep numbers by default; reassign at acceptance if chosen; the old number stays in history.

## NEW data
Settings (prefix, length, year flag, per-Section prefixes), a sequence source, a history table of old numbers.

## Tests
Unique constraint school-wide; allocation is safe under concurrent saves; a format change never alters issued numbers; a prefix with spaces or symbols is refused server-side; only the permitted role can save.

## Open questions
Is the sequence **one school-wide counter or one per prefix**? Can the next number be set (e.g. start at 0500)? Which year (calendar year at creation, or the session year)? Maximum length? Does a number kept in history block reuse?


## Follow-up changes — Batch 6 reissue (2026-10-31)

# Registration Numbers — Batch 6 follow-up

Three-level prefix Level > Section > school, blank fallback; examples for each level. Movement policies: keep digits/change prefix (Oakfield default, school-wide counter), new prefix counter, keep whole number. Lock after first issuance. 3–6 minimum padding, default 4, overflow grows. Next counter cannot fall below highest issued. Historical numbers never reused. Calendar year at creation. Awaiting admission number edits Admin-only; current Section Head otherwise; Admin any pupil. Movement on Incoming acceptance; Graduated retains last number.

Design-only HTML is a reference; Backend Agent must confirm API and permissions before Codex implementation.


### Confirmed correction

Navigation: Administration, not Academics. Level > Section > school prefix fallback. 3–6 minimum zero-padding default 4; counter overflow grows. Movement policy locks after first issued number; historical numbers never reused.
