# Browser QA database

Browser checks use only the guarded `qa:prepare` command. Tests continue to
use SQLite `:memory:` through `phpunit.xml`.

From PowerShell in the project root:

```powershell
$qaPath = Join-Path (Resolve-Path database).Path 'qa.sqlite'
& 'C:\xampp\php\php.exe' artisan qa:prepare $qaPath --serve --port=8000
```

The command accepts only an absolute path whose resolved file name is exactly
`qa.sqlite` in this project's `database` directory. It refuses production,
relative or unresolved paths, other file names, and the persistent
`database/database.sqlite`. It prints both the resolved path and the live
SQLite connection before it runs migrations or `DemoDataSeeder`.

`--serve` starts PHP's built-in server directly with these effective environment
values. It does not nest `artisan serve`, whose environment allow-list can drop
database overrides:

```text
DB_CONNECTION=sqlite
DB_DATABASE=<the printed absolute database/qa.sqlite path>
OAKFIELD_QA_ACTIVE=1
OAKFIELD_QA_DATABASE=<the same absolute path>
```

Before announcing the server, the launcher requests the QA-only live proof from
the served application and compares its resolved database file with the exact
absolute `qa.sqlite` path. The proof route is registered directly by the
application provider, outside the stateful `web` group, so it has no session,
cookies, CSRF or authentication and remains available independently of cached
web routes. It returns only `PASS` plus the resolved path, or `FAIL`. A missing
marker, missing proof or path mismatch stops the child server and fails the
command. No browser login, fixture access or automation may happen until `Live
HTTP proof: PASS -> <absolute path>` is printed.

Stop the server after the check and delete only the printed `qa.sqlite` file.
Never migrate, seed, or run browser QA against `database/database.sqlite`.
Record the development database's SHA-256 hash, size and modified time before
the QA session, compare all three afterwards, and investigate and report any
difference as an isolation incident.

For local Paystack timing checks, run `php artisan schedule:work` in a separate
terminal. A local server normally cannot receive Paystack's webhook, so a
successful payment may remain **Still confirming** until the scheduler runs or
the authorised payer, Admin or Bursar presses **Check again**.
