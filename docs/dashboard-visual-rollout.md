# Dashboard visual rollout — reference and continuation record

Status: **Completed (2026-10-05)**. This is a post-Phase-4 visual refinement,
not the start of Phase 5 portals or notifications. Update this file and
`PROGRESS.md` as each item is verified.

## Supplied references

The client supplied three self-contained HTML mockups in Downloads:
`oakfield_admin_dashboard_mockup.html`,
`oakfield_teacher_dashboard_with_sidebar.html`, and
`oakfield_teacher_dashboard_mobile.html`; three corresponding screenshots
(`oak 1.png`, `oak2.png`, `oak3.png`); and the actual square Oakfield logo
(`images (1).jfif`). The HTML's final `#vis-container` is the visual
reference. Its surrounding exported application CSS, CDN icon link, and
sample buttons are not application instructions or dependencies.

## Visual contract

- Desktop Admin: 170px deep-teal sidebar with grouped Teaching, Daily ops,
  and Administration links; cream active item; brand at top; sign-out at
  bottom. Warm `#FBF8EF` content with a serif greeting, role pill, four
  `#F1EAD8` metric cards, three quick actions, and a compact pending-result
  table. Match spacing, shapes, color roles, and typography from the
  supplied HTML, not its hardcoded sample values.
- Desktop Teacher: 150px sidebar with Dashboard, Attendance, Results,
  Assignments, and E-Notes; same brand and active treatment. Content has
  greeting/class-subject context, Attendance and Enter scores actions,
  three metrics, and a compact roster with textual status pills.
- Mobile: no sidebar; teal brand/greeting band; two stacked-label action
  tiles; three metric cards; readable roster rows; fixed white bottom bar
  with at most five items, using More for overflow. The mockup is a 300px
  illustration, not a fixed-width app viewport.
- Use the supplied real logo in shared dashboard chrome and the public
  homepage. Keep the school-profile data source authoritative so a later
  school-specific upload can replace the default. The full logo should
  appear at readable size on the homepage; compact chrome may show the
  emblem crop without altering the source image.
- All other built role dashboards share these visual primitives but expose
  only routes and data already implemented and authorized. Parent/Student
  full portal content, notifications, and unbuilt debtor pages remain
  Phase 5 work. Never substitute the mockup's sample people/counts as
  actual records.

## Implementation sequence

1. Add the supplied logo as a versioned project asset, expose it through
   `CurrentSchoolProfile` as the single-school default when no uploaded
   `school_settings.logo_path` exists, and retain uploaded-logo precedence.
   Apply it to homepage, sign-in/public chrome, sidebar/mobile chrome, and
   existing PDF branding via the same service.
2. Refine the shared persistent shell: desktop brand in the sidebar, grouped
   permission-driven links, a narrow 150px sidebar (170px for dense Admin
   navigation), sign-out pinned at bottom; mobile greeting band and tab bar.
   Keep `wire:navigate`, `@persist`, active-link state, keyboard focus, and
   the teal navigation progress indicator.
3. Replace the generic `/dashboard` link list with Admin and Teacher
   dashboard compositions based on permissions/profile data, plus a safe
   shared fallback for other current users. Use module services for
   cross-domain counts/rosters/payments/results; paginate any list view,
   and never use role names as authorization checks.
4. Align the homepage's logo sizing and the common dashboard typography,
   spacing, cards, actions, tables, status pills, and mobile breakpoints
   with the supplied HTML. Update `docs/design-system.md` so future phases
   use this visual contract rather than the old generic dashboard.
5. Verify desktop and mobile renders against the references, including real
   data/empty states, working links, no horizontal overflow, and logo
   readability. Run Blade cache, Vite production build, PHP lint/Pint, and
   the existing trust-sensitive suite. Do not add broad UI tests by default.

## Boundaries and decisions

The references show illustrative values (412 students, 28 teachers, 94%
attendance, three named result rows). Actual UI values must come from
tenant-scoped records; an empty data set gets an explicit empty state.
The mockup's `View debtors` label may link to the existing unpaid-invoice
view only if that view genuinely filters unpaid records; otherwise record
it as not yet available. No new Phase 5 debtor module is implied.

`docs/design-system.md` remains the authoritative reusable specification.
This file records the source comparison, implementation status, and
verification evidence for handoff.

## Verification log

- 2026-10-04: Reference HTML and screenshots inspected. The supplied JPEG
  is versioned as `public/branding/oakfield-logo.jpg`; local profile has
  no uploaded logo, so the default resolves in dashboard/public chrome,
  homepage, and print profile. Shared desktop sidebar/mobile band and
  five-tab navigation were implemented. Admin and Teacher dashboard
  compositions use tenant-scoped module services; no sample people or
  figures were copied from the HTML. Other built users retain a safe
  permission-driven link dashboard. Existing Admin and Teacher accounts
  each rendered successfully against the local database with the logo
  and mobile-tab markup present. The first screenshot exposed a persisted
  wrapper/grid placement bug; `display: contents` fixed the desktop sidebar.
- 2026-10-05: The local Teacher's permission roles were split, so `/dashboard`
  was denied despite Attendance and Results access. Two tenant-scoped RBAC
  data migrations now grant `view_dashboard` to teaching/attendance roles;
  the Teacher account renders the five-tab dashboard. The 1480px Admin
  desktop, 1480px Teacher desktop, and 500px Teacher mobile views were
  inspected against the supplied references. Logo, sidebar, two-action
  layout, metrics, roster, and mobile bottom tabs fit with no horizontal
  overflow at the verified widths. Real local data remains intentionally
  different from the mockup's sample counts/names. Today's percentage is
  suppressed until the whole relevant roster is marked. PHP 8.2/GD suite
  passed (11 tests / 251 assertions); Pint, Blade cache, and Vite production
  build passed. Temporary HTML/screenshots/browser profiles were removed.

The screenshots were a visual regression check against local static renders;
interactive `wire:navigate` behavior relies on the existing persistent
shell and should be smoke-checked again on the eventual live host. This
does not start Phase 5 or alter its entry/exit criteria.
