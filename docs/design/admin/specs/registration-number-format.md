# Spec: Registration number format (composition) — `screens/registration-number-format.html`

Status: **New design reference; not implemented by this package.** Role: Admin (same permission as the existing Registration numbers page; Codex matches). Written 2026-11-03 at the owner's request.

## What this replaces
It supersedes the **"Number format"** and **"Prefixes by Section and Level"** cards of `registration-numbers.html` (a single school prefix plus per-Section and per-Level prefixes with fallback). The movement-policy and history/permission cards of that page continue, and the policy card is repeated here with examples that follow the chosen format. Admin and Head change rights, Awaiting-admission rules, number history and everything in `registration-movement-preview.md` are unchanged.

## Design
One page, four numbered steps and a live preview that stays visible on desktop:
1. **What goes in the number.** Five parts in a **fixed order: School, Section, Level, Year, Number.** The owner can tick any combination of School, Section, Level and Year. Number is always included and last. Also: **School code** (shown only when School is ticked), **Separator** (hyphen recommended, slash, dot, none), **Number length** (3 to 6, zero-padded; numbers grow by one digit instead of stopping, e.g. 9999 to 10000), **Year style** (shown only when Year is ticked: 4 digits or 2 digits; the calendar year at issue; never changes afterwards).
2. **Codes.** Shown only when Section and/or Level is ticked. One code per Section (if Section is ticked) and one per Level (if Level is ticked), 1 to 6 letters or numbers, upper-cased. Each row shows OK, Missing or Used twice.
3. **Next number.** Can be raised, never lowered below the highest number already issued. Shows the highest issued.
4. **When a pupil moves.** Three choices (keep digits and change prefix [Oakfield default, one school-wide counter]; new number from the new prefix's counter; keep the whole number). Examples are generated from the current format. Locked once any pupil has a number. If neither Section nor Level is in the format, a note says moving never changes a number and the choice has no effect.

**Preview** shows a chip strip (parts that are off are struck through), plus a Primary 1 and a JSS 1 sample number. A missing code shows as a red "?".

## Rules
- Turning Section on requires a code for **every** Section; turning Level on requires a code for **every** Level. Missing codes block Save and list what is missing.
- Codes: Sections must be different from each other; Levels must be different within a Section; if Section is **not** in the number, Level codes must be different across the whole school (otherwise two Levels would give the same prefix).
- The school code is 2 to 6 letters or numbers when School is ticked.
- Unticking a part does not delete its codes (so ticking it again restores them).
- **Changing the format affects new numbers only.** Issued numbers never change (as in the earlier spec). Reused numbers are still refused: the full number is unique school-wide and numbers a pupil once held stay reserved.
- The movement policy **locks after the first number is issued**; the format, codes and next-number floor remain editable, with a warning banner: "Pupils already have numbers..."
- The number never encodes anything else about the pupil.
- Server-side validation for every rule above; the design's disabled Save is a convenience, not the enforcement.

## States in the HTML (design-only toolbar)
Setting up (nothing issued) · Numbers issued (policy locked, next number floor 413) · Codes missing (Save blocked, summary and red rows) · Saved (confirmation with a sample number). The toolbar and the "Design reference" note are not part of the build. The sample Sections, Levels and codes (PRI, SEC, NUR, P1, P6, J1, J3, S3) are illustrations only; the real page lists every Section and Level.

## Layout and responsive
Desktop: preview card sticks at the top; part tiles in a row; codes grouped by Section with indented Levels. At 390px: one column, no horizontal scrolling, every control at least 44px high. Checked in a browser at both widths with no overflow.

## Backend Agent must confirm before Codex builds
1. What the 6B build stores today (school prefix, length, year flag, Section and Level prefixes, next number, policy, lock) and how to migrate it to the new settings (parts flags, separator, year style, school code, Section and Level codes). Existing prefixes should map to codes so no number changes.
2. Whether the allocation code can build the number from the chosen parts and keep one school-wide counter (policy 1) or one counter per distinct prefix (policy 2) when the prefix now has up to three components.
3. Whether a 2-digit year style and the separator options are acceptable for every place the number is shown, searched or printed (Record payment search, invoices, receipts, report cards, sign-in slips, imports). If a slash or dot breaks any of them, drop that option.
4. How a changed format interacts with duplicates: the full string must stay unique school-wide, and the guard must stay inside the existing race-safe allocation.
5. How Level-only or Section-only formats behave for pupils whose Level has no Section (the Level requires a Section per Go-Live).

## Tests (trust-sensitive only)
A part cannot be ticked while any required code is missing (server refuses); duplicate codes refused; changing format never alters an issued number; no number is ever reused; concurrent allocation stays unique under the new format; policy cannot change after the first number; next number cannot be lowered below the highest issued; only the permitted role can save.

## Open questions (for the owner, not blocking the design)
Whether the Year should be the calendar year at issue (as designed and as in the earlier spec) or the school session year.
