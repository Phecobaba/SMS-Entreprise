# Oakfield School Management System

A web-based school management system built for Oakfield International
Schools, covering pre-nursery through secondary levels.

## Project Status

**Phase 4 is complete and Phase 5 is substantially built, but Phase 5 is not
formally complete.** Owner-directed Admin refinement is active. See
`PROGRESS.md` for the authoritative live build status and ordered next work.

## Stack

- **Backend:** Laravel (PHP)
- **Frontend:** Blade templates + Livewire (reactive components) + Alpine.js
- **Database:** MySQL
- **Hosting:** cPanel (shared-hosting compatible)

See `ARCHITECTURE.md` for the full reasoning behind these choices.

## What This System Does

Covers the full day-to-day running of the school: admissions (entrance
exams through enrollment), computer-based testing, result management with
an approval workflow, a parent/student portal, fee payments, attendance,
assignments, e-notes, and staff HR records. See `ROADMAP.md` for the exact
scope breakdown of what's included in this build versus deferred to later.

The system is built **SaaS-ready but deployed single-tenant** — meaning
every table is structured so a second school could be added later without
a rewrite, but no multi-school, billing, or subscription functionality is
built or active in this deployment. See `ARCHITECTURE.md` §2 for why.

## Documentation Map

Start with `AGENTS.md` if you're an AI coding agent — it's short and
links to everything below. For everyone else:

| File | Purpose |
|------|---------|
| `ARCHITECTURE.md` | How the system is structured: stack, tenancy, module boundaries, RBAC, performance and testing rules |
| `ROADMAP.md` | Product scope: what's in the MVP, what's deferred, and why |
| `PHASES.md` | Engineering build order (Phase 0 through Phase 5), with entry/exit criteria for each |
| `docs/schema.md` | Every database table, grouped by module |
| `CONTRIBUTING.md` | How to pick up a task and what "done" means |
| `PROGRESS.md` | Live build status — what's done, in progress, and next |

## Local Setup

Requirements: PHP 8.2+, Composer 2, Node.js, npm, and the PHP SQLite and GD
extensions for the default local setup.

On Windows/XAMPP, confirm which configuration the command-line PHP uses with
`php --ini`, then enable `extension=gd` (remove its leading semicolon) in that
loaded `php.ini`. Restart any open terminals and verify with
`php -m | findstr /I gd`. The test suite uses GD for image-validation paths,
so the default `php artisan test` command requires it.

```text
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
npm install
npm run build
php artisan serve
```

Run checks with `php artisan test`. Production deployment instructions are
in `docs/deployment.md`.

For the local demo dataset, set these local `.env` keys to passwords of at
least 12 characters before running `DemoDataSeeder`: `DEMO_ADMIN_PASSWORD`,
`DEMO_TEACHER_PASSWORD`, `DEMO_PRINCIPAL_PASSWORD`, `DEMO_BURSAR_PASSWORD`,
`DEMO_STUDENT_PASSWORD`, `DEMO_PARENT_PASSWORD`, and
`DEMO_CANDIDATE_PASSWORD`. Values stay only in `.env`; never commit them.

Staff temporary passwords use `TEMPORARY_PASSWORD_BCRYPT_ROUNDS` (default
`10`) so one-time account creation and resets are cheaper than the normal
`BCRYPT_ROUNDS` setting. Passwords chosen by users always use the normal cost.
User-chosen passwords are at least eight characters and cannot be the username,
school name, current/temporary password, all digits, or a listed common
password. Production deployments require `SESSION_DRIVER=database` so account
changes can revoke other sessions reliably.
The seeder is idempotent and refuses to run outside `APP_ENV=local`.

Tests use SQLite in-memory and fail fast if configured otherwise. Browser or
manual QA must use a throwaway, gitignored SQLite file such as
`database/qa.sqlite` through an environment override; migrate/seed that file
for the check and delete it afterwards. Never run `migrate:fresh`, tests, or QA
seeding against the persistent `database/database.sqlite` development file.

After configuring the school ID and database, provision the first account
interactively with `php artisan oakfield:bootstrap-admin "School Name"
"Admin Name" admin@example.com`. The command prompts for the password.
For a school administrator created before the Academics module was added,
run `php artisan oakfield:grant-academic-access` once to grant the new
`manage_academics` permission. New administrators receive it during
bootstrap.
To edit the public school profile, existing administrators should also run
`php artisan oakfield:grant-school-profile-access`; new administrators
receive this permission at bootstrap too.

## Open Business Questions

Two things are not yet resolved and are **not engineering decisions** —
see `PROGRESS.md` → "Blockers / Open Questions" for current status:

1. Whether the original 12-week timeline holds against the scope as it
   has grown since the original estimate.
2. Whether the original ₦2.3M MVP price holds against that same scope
   growth.
