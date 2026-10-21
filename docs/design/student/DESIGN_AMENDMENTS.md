# Design amendments (student batch)

Where this file and `docs/design-system.md` differ, **this file wins** until the changes are merged into
`design-system.md` (Codex does that merge; see `CODEX_BRIEF.md` step 1).

## 1. Contrast fixes (measured, WCAG AA needs 4.5:1 for normal text)

| Use | Current | Ratio | Replace with | Ratio |
|---|---|---|---|---|
| Success pill text on `#EAF3DE` | `#3F7D63` | 4.25 | `--color-success-ink` `#27500A` | 8.2 |
| Big green numbers on surface `#F1EAD8` | `#3F7D63` | 4.05 | `--color-success-num` `#2E6B50` | 5.25 |
| Ochre **text** (secondary buttons, metric accents) on bg | `#C77B3F` | 3.12 | `--color-ochre-ink` `#9A5A22` | 5.12 |
| Inactive mobile tab on white | `#9A9589` | 2.99 | `--color-tab-idle` `#6F6A5E` | 5.39 |

`#C77B3F` stays for borders, dots and badges (non-text). Warning, danger, muted text and cream-on-teal already pass.
Also add `--color-sidebar-text #D9E6E3`, `--color-sidebar-rule #3E6168`, `--color-band-muted #B9CBC8` (already used in the app as literals).

## 2. New components (all shown in `screens/components.html`)

`oak-ticker` (notification ticker), `oak-banner` (open-exam call to action), `oak-notice-{info,success,warning,danger}`,
`oak-locked` (payment-locked card), `oak-scrim` + `oak-sheet` (modal on desktop, bottom sheet on phone), `oak-toast`,
`oak-field` (form field + inline error), `oak-pager`, `oak-exam-row`, `oak-status-neutral` and `oak-status-new` pills,
`oak-stack` (table → two-line rows on phone), `oak-btn` family (44px minimum). Exam-only classes (`ex-*`) are in `oak-exam.css`.

## 3. Navigation

- Student order becomes **Dashboard, Exams, Results, Assignments, Attendance, Fees, E-Notes** (+ profile, sign out).
  `_shell-start` shows the first 4 items on the tab bar (Home, Exams, Results, Work) and puts the rest in More, and the sidebar uses the same list,
  so this one order drives both. Assignments is labelled "Work" on the tab bar only.
- The phone **More** menu gains Notifications and My profile (today it only adds Sign out) and shows "N new" pills.
- Today's student `AppNavigation` links to `dashboard#results`, `#attendance`, `#payments` anchors. The design uses real pages
  (see specs) so the active tab highlights correctly.
- Sidebar stays 150px (the early student mockup used 160px; 150px matches `app.css` and Teacher).

## 4. Notification ticker (shared by every role's dashboard)

Slim strip at the top of the content area (desktop) / directly under the teal band (phone). Source: the signed-in user's own
**unread** rows in `notifications` (latest 5, title + body). Hidden when there are none. Not shown on the exam-taking screen.
Desktop scrolls slowly; hover, keyboard focus or the pause button stops it. Phone shows one headline at a time (5s).
`prefers-reduced-motion`: no animation, a scrollable list. Each item links to `/notifications`. It is not an announcement
system; a school-wide broadcast would need a separate admin screen and table (not designed).

## 5. Type and touch

Mockup font sizes (9.5–10px) were illustration-scale. Real minimums: body 16px (15px in dense tables), labels 12.5px, pills 12.5px,
tab labels 11.5px. Every tap target ≥ 44px, including text links and the navigator cells on phones (`.oak-link` gets a 44px hit area at ≤767px or on coarse pointers); exam answer rows ≥ 56px. Every icon in navigation has a text label.
Money shows whole naira without decimals when the amount is exact (`₦45,000`), otherwise two decimals.

## 6. Icons

Inline SVGs from Tabler (MIT), outline, 2px stroke. Each carries `data-icon="<tabler-name>"` so they can be swapped.
The app's `_nav-icon.blade.php` uses hand-drawn SVGs; replace its `@if` chain with a small `<x-icon name="…">` component
that prints the SVG for a name. Do not add a CDN or an icon font.

## 7. Wording

Plain verbs, sentence case. "Check later" (not "Flag for review"). "Start exam", "Resume exam", "Submit exam", "Go back".
Errors say what happened and what to do; they never apologise.

## 8. Batch 2 additions (all in `shared/oak-student-batch2.css`)

`oak-chip` / `oak-chip-nav` (44px filter chips with `aria-pressed`), `rs-*` (exam result hero, answer review, waiting state),
`rc-*` (report card header, facts, subject table that becomes card-rows on phones, comment blocks), `at-*` (attendance strip: every cell carries a letter),
`oak-row` / `details.oak-row` (list rows with an action; expandable rows), `nt-*` (notification rows), `fe-summary` (fees),
`pf-*` (profile list), `lg-*` (guest login layout; full logo panel on desktop, teal band on phones).

## 9. Corrections to batch 1 wording

- The ticker sample items in `screens/dashboard.html` mentioned exam and receipt notices that the app does not send. The app sends exactly three kinds today:
  **New assignment**, **Result updated**, **Payment confirmed** (title + body, no link). Keep the ticker as built; only the sample copy was illustrative.
- Fees rows use a **secondary** button; the single primary "Pay" lives in the page summary (one primary per view).

## 10. Batch 3 additions (`shared/oak-parent.css`)

`oak-ward-bar` / `oak-ward-opt` (child bar and switcher), `fe-*` (progress line, line-item table with total, part-payment rows, receipts, "visit the bursar" line),
`oak-status-await` ("Awaiting approval" pill: dashed ochre border plus a clock icon, so it never relies on colour). Fees invoice rows use `details.oak-row.fe-inv` and stack on phones.
Parent navigation order is its own list (Dashboard, Results, Attendance, Fees, Assignments); Fees is on the phone tab bar because paying is the parent's main task.
The ward switcher is a button and dialog, never an auto-submitting `<select>`.

## 11. Review changes after batch 3

- **Pager rule:** render the pager only when there is more than one page (Laravel `links()` already does this). Never show "Page 1 of 1" with an enabled Next.
- **Ward-switcher pill:** state-driven (see `specs/parent-ward.md`); sample data in the design files never shows "Fees due" next to ₦0.
- **Awaiting approval** (`oak-status-await`) is now used on the Fees screens (parent and student), not only in the component sheet.
- Parent notification and ticker text names the child ("For Jane: ...").
