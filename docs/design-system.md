# Design System

> This is the single source of truth for visual design across every
> dashboard (Admin, Teacher, Principal, Parent, Student, Bursar) and the
> printable Result Card. One shared system, different content per role —
> do not create a role-specific color scheme or component style. If a new
> screen needs something not covered here, extend this file in the same
> session you build it, don't improvise and leave it undocumented.

## Brand Source

The supplied Oakfield logo is versioned at `public/branding/oakfield-logo.jpg`.
It is the single-school fallback when no valid `school_settings.logo_path`
upload exists. Dashboard chrome crops the emblem into a circle; the homepage
shows the complete square logo and motto. See `docs/dashboard-visual-rollout.md`
for the client references and verification record.

Colors are sampled directly from Oakfield's actual logo (deep teal
background, cream pine-cone/palm emblem), not invented. See `README.md`
for the logo file if it needs to be re-referenced.

## Color Tokens

| Token | Hex | Role |
|---|---|---|
| `color-bg` | `#FBF8EF` | Page background |
| `color-surface` | `#F1EAD8` | Card/section backgrounds, metric cards |
| `color-text` | `#1F3638` | Body text (a darkened tint of the brand teal) |
| `color-text-muted` | `#5B6B6A` | Supporting/secondary text, table labels |
| `color-brand-teal` | `#2E4F56` | Primary — header/sidebar/nav band, primary buttons, active nav state |
| `color-brand-cream` | `#F5E58A` | Logo accent — badges, role pills, active-item highlight. **Never body text or large fills** (fails contrast on light backgrounds) |
| `color-accent-ochre` | `#C77B3F` | Secondary actions (outlined buttons), gentle emphasis |
| `color-success` | `#3F7D63` (text) / `#EAF3DE` (bg) | Present, Approved, Paid |
| `color-success-ink` | `#27500A` | Contrast-safe success-pill text on `color-success` background |
| `color-success-num` | `#2E6B50` | Contrast-safe large success numbers on light surfaces |
| `color-warning` | `#633806` (text) / `#FAEEDA` (bg) | Late, Pending |
| `color-danger` | `#791F1F` (text) / `#FCEBEB` (bg) | Absent, Overdue, Rejected |
| `color-ochre-ink` | `#9A5A22` | Contrast-safe ochre text; keep `color-accent-ochre` for borders, dots, and badges |
| `color-tab-idle` | `#6F6A5E` | Inactive mobile-tab text and icons |
| `color-sidebar-text` | `#D9E6E3` | Inactive text on the teal sidebar |
| `color-sidebar-rule` | `#3E6168` | Sidebar dividers |
| `color-band-muted` | `#B9CBC8` | Supporting text in the teal mobile band |
| `color-border` | `#DDD3BA` | Default hairline borders/dividers |
| `color-border-light` | `#EFE9DA` | Table row dividers (lighter than standard border) |

Status colors (success/warning/danger) always pair a light background tint
with the matching darker text shade from the same row above — never plain
text color on a status pill.

## Typography

- **Headings / display**: a classic serif echoing the logo's wordmark —
  Georgia or Times New Roman as the safe system-font fallback; if a web
  font is added later, something in the Fraunces/Playfair/Lora family.
  Used for page greetings ("Good afternoon, Mrs. Adeyemi"), the school
  name in headers, and the Result Card's school name line.
- **Body / UI**: system sans-serif stack (`-apple-system, 'Segoe UI',
  Roboto, sans-serif`) for everything else — buttons, tables, labels,
  form fields. Prioritizes legibility on dense screens (rosters, result
  tables) over personality.
- Two weights only in practice: regular body text, and slightly heavier
  for headings/numbers — don't introduce a third weight.

## Navigation Pattern

Client dashboard reference update (2026-10-04): desktop brand is at the top
of the left sidebar, with no second desktop header. Use 150px for compact
Teacher navigation and 170px for dense Admin navigation; dense Admin links
are grouped Teaching, Daily ops, and Administration. Mobile hides the
sidebar and uses a teal brand/greeting band and a fixed white bottom bar.
Teacher's five tabs are Home, Attendance, Results, Work (Assignments),
and More (E-Notes and overflow). Keep `wire:navigate`, `@persist`, and the
teal progress indicator on both layouts.

- **Desktop**: persistent left sidebar, `150px` wide, `color-brand-teal`
  background. Nav items differ per role (see "Per-Role Nav Items" below).
  Active item: `color-brand-cream` background, `color-brand-teal` text.
  Inactive items: light teal-tinted text (`#D9E6E3`) on the dark sidebar.
  Sign-out pinned to the bottom, separated by a hairline divider.
- **Mobile**: bottom tab bar, white background, max **5 tabs**. Active
  tab icon+label in `color-brand-teal`; inactive in a neutral grey
  (`color-tab-idle`). A 5th "More" tab holds any role's overflow destinations
  beyond the first 4 — don't cram more than 5 icons into the bar.
- **Navigation feel**: implemented via Livewire's `wire:navigate` —
  content swaps without a full page reload; sidebar/header/tab bar persist
  across navigations via `@persist` and do not re-render. Progress
  indicator color is `color-brand-teal`, not Livewire's default. See
  `ARCHITECTURE.md` for the technical implementation note.

### Per-Role Nav Items (reference, extend as new modules are built)

| Role | Items |
|---|---|
| Teacher | Dashboard, Attendance, Results, Assignments, E-Notes |
| Bursar | Dashboard, Payments |
| Principal | Dashboard, Academics (Classes, Bulk subjects, Teacher assignments, Promotion), Results (approval), Attendance |
| Admin | Dashboard, Academics, Admissions, Results, CBT, Attendance, Assignments, E-Notes, Payments, Staff HR, School Profile |
| Parent | Dashboard, Results, Attendance, Payments, Assignments |
| Student | Dashboard, Exams, Results, Assignments, Attendance, Fees, E-Notes |

Navigation items above are a role-level product reference. In the current
single-school build, show only destinations with an implemented route and
permission/profile access; do not invent Phase 5 dashboard, debtor, or
portal pages just to fill a tab. The mobile bar displays up to four
destinations plus a More tab for overflow. Persisted links use Livewire's
`wire:current`/`data-current` state so the active highlight updates after
`wire:navigate` without re-rendering the chrome.

For Student, the phone tabs are Home, Exams, Results, Work, and More. Work
is the phone-only label for Assignments. More contains Attendance, Fees,
E-Notes, Notifications, My profile, and Sign out. Until the corresponding
Student screens are designed, these destinations may use the existing
dashboard anchors rather than invented pages.

## Component Patterns

Promotion uses the shared Admin shell, `ad-tabs`, cards, notices, fields,
buttons and pager. Admin sees a Section filter; a Section head sees a fixed
Section badge and the sentence that they can act only inside that Section.
Until every Section has a saved Class arrangement marker, the screen is a
locked card headed “Arrange your classes first”; a head whose own Section is
already arranged is told which other Section is outstanding. Normal class
promotion uses a paginated dense roster, editable destination Class and Arm
per Student, and a bulk bar. Review always states “This can't be undone” and
shows counts produced by the same server plan used by Apply. Final classes use
the same roster and review pattern, with an `Awaiting admission` or
`Graduated` destination status instead of invented Section ordering. On phone,
each roster row stacks to one column and every interactive control remains at
least 44px.

Students in `Awaiting admission` use the existing neutral no-access/locked
pattern outside Results and Receipts. Their navigation is limited to Dashboard,
Results and Receipts; the Fees page becomes receipt-only and never renders a
Pay action. Graduated pupils, and parents viewing them, additionally retain
read-only, payment-gated Attendance for the last 30 recorded school days.

Dashboard compositions: Admin shows four real-data metrics (active students,
distinct assigned teachers, Morning/Afternoon attendance, unpaid tuition
invoices), three actions (Admissions, Staff HR, invoices), attention rows,
and **Submitted-only** result approvals. Its All scope is the sum of Section
values only for additive figures; teacher counts remain distinct. The Section
switcher appears only with `view_all_sections` and more than one Section.
A Head sees the same Section-scoped attendance/result primitives for their
assigned Section, with a badge instead of a switcher; no Section assignment
produces the locked state on both dashboard and attendance alerts. The Bursar
sees whole-school current-term outstanding/collected figures, Record payment,
and recent payments, without a ticker or a Section switcher. Awaiting approval
and Not invoiced remain absent until payment batch 5 exists. Teacher shows two
actions, three real-data metrics, and up to eight form-arm roster rows; Pending
is that teacher's submitted results awaiting approval. Morning and Afternoon
dashboard figures come from the shared Attendance service, show Not held when
excluded, and otherwise show an em dash until every active learner in every
held Arm is marked. Empty data uses an explicit empty state or em dash, never
the example values/names in the client HTML.

- **Header band**: `color-brand-teal` background, logo mark (circular,
  cream background with teal "O" or the real logo image once available)
  + school name in serif type, cream. Appears identically across every
  role — this is the one piece of chrome every user shares.
- **Primary button**: `color-brand-teal` fill, `color-brand-cream` text,
  `8px` border-radius, no border.
- **Secondary button**: `color-bg` fill, `color-accent-ochre` text and
  `1.5px` border, same radius as primary. Use for the second-priority
  action next to a primary button (e.g. "Enter scores" next to "Mark
  attendance") — never two primary buttons side by side.
- **Metric cards**: `color-surface` background, no border, `8px` radius,
  small muted label above a larger number. Grid of 2–4, not more.
- **Status pills**: rounded-pill (`999px` radius), background/text pair
  from the status color tokens above. Used for attendance status, payment
  status, result status.
  The shared Blade status-pill partial maps positive states (for example
  Paid/Approved/Present), negative states (Absent/Rejected/Failed), and
  other pending states to the three paired token sets; text remains visible.
- **Dense data (rosters, result tables, payment lists)**: real HTML
  tables with hairline row dividers (`color-border-light`), not cards
  pretending to be rows. Deliberate choice — do not "card-ify" tabular
  data.
- **Role pill** (top-right of header on mobile/desktop): cream background,
  teal text, showing the current user's role (e.g. "Teacher").
- **Notification ticker**: on dashboards only, show the signed-in user's
  latest five unread notifications directly below the desktop content edge
  or mobile teal band. Hide it when empty. Desktop motion pauses on hover,
  keyboard focus, or its pause button; phone rotates one item every five
  seconds. Respect reduced motion. It is never shown in exam focus mode.
- **Student action patterns**: share the banner, four notice tones, locked
  card, modal/phone sheet, toast, field, pager, exam row, neutral/new pills,
  stacked phone table, and button family across Blade partials. Exam-only
  `ex-*` patterns remain part of the same `resources/css/app.css` file.
- **Admin academic patterns**: use the `ad-*` family in the shared
  `resources/css/app.css`: scrollable page tabs, segmented Section switcher,
  bordered structure cards, level/class rows, Department tags, and shared
  sheets. Class order uses 44px `ad-iconbtn` Move up/Move down controls and a
  one-time Arrange form; stored position numbers are never displayed. Parallel
  Department Classes share one step, move together, and only the step leader
  has move controls. Admin navigation is 170px and grouped; its link region scrolls
  independently while the school brand and Sign out remain pinned.
- **Shared dashboard patterns**: keep `ad-metrics`, `ad-attn`, `ad-actions-row`,
  `ad-action`, and `ad-queue` in `resources/css/app.css`; do not add a second
  Admin stylesheet. On short desktop viewports the brand and Sign out remain
  pinned while `.oak-sidebar-links` owns the vertical scroll.
- **Bulk academic assignment patterns**: the five-step Subject flow uses the
  shared `ad-stepper` on desktop and a short “Step N of 5” label on phones,
  then shows server-computed Added/Skipped/Failed figures. Teacher assignment
  uses By teacher and By class tabs, server-computed Add/Remove/Unchanged
  review figures, and offered Class/Subject pairs only. A 20-plus-subject
  desktop matrix scrolls inside a labelled, keyboard-focusable region with a
  sticky header and frozen Class column; on phones it becomes one Class card
  containing offered Subjects only. Head variants replace the Section switcher
  with a fixed Section badge and use the shared “No section assigned” lock.
  All controls retain the 44px target floor and all styles remain in
  `resources/css/app.css`.
- **Section switcher**: render only for a user with `view_all_sections` and
  only when the school has more than one Section. A head sees a read-only
  Section badge instead. The query selection is validated server-side and
  remembered in the session.

## Accessibility, Touch, and Icons

- Interactive phone targets, including text links and exam navigator cells,
  are at least `44px`; exam answer rows are at least `56px`.
- Use labelled inline Tabler SVGs through the shared Blade icon component.
  Do not add an icon CDN or font, and never use an unlabelled navigation icon.
- Use plain, sentence-case actions such as “Check later”, “Start exam”,
  “Resume exam”, “Submit exam”, and “Go back”.
- Format exact money amounts as whole naira (for example `₦45,000`), using
  two decimals only when the value includes kobo.

## Result Card (Printable A4 PDF)

- White background (not the warm app background — this is a printed
  document, not a screen), `color-brand-teal` as a single rule/divider
  under the letterhead, not a full colored band (keeps toner/ink cost
  sensible for schools printing many of these).
- Letterhead: logo mark + school name (serif) + address + motto, exactly
  as sampled from the real logo.
- Subject table includes the per-subject remark column
  (`show_subject_remarks`, default **on** — see `ARCHITECTURE.md` §10 and
  `docs/schema.md`), sourced from `grade_bands.remark`, not teacher-typed.
- Three comment fields: Form Teacher's Comment (form-teacher-permission
  only, see `ARCHITECTURE.md` §12), Principal's Comment, Proprietor's
  Comment — each supports personalized or term-default text.
- Must fit a single A4 page against the heaviest template (Secondary,
  most subjects) — this is a hard constraint, not a visual preference.
  See `ARCHITECTURE.md` §10 for the full requirement.

## Accessibility Floor (non-negotiable, not optional polish)

- Status must never be color-only — pills carry text ("Present",
  "Overdue"), not just a colored dot.
- All interactive elements need visible keyboard focus states.
- Text on colored backgrounds (pills, badges) always uses the darker
  paired shade from the Color Tokens table — never plain black/white on a
  tinted background.
- Responsive down to mobile per the bottom-tab-bar pattern above — no
  screen should be desktop-sidebar-only with no mobile equivalent.

## What's Still a Placeholder

- The real Oakfield logo image (not the sampled color swatch) needs to be
  used directly once supplied in production-ready format — current work
  uses a circular teal/cream placeholder mark standing in for it.
- Exact serif web font (if one gets added beyond the system fallback) is
  not yet chosen — Georgia/Times New Roman fallback is acceptable for now.
