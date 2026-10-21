# cPanel Deployment

**Proof status:** Phase 0 requires a local-equivalent SSH deployment and
rollback under cPanel-like constraints. The production steps below are for
the purchased cPanel account at project end. A local pass does not prove the
live host; the live `/up`, PHP, cron, and permission checks remain on the
`PHASES.md` Go-Live Checklist.

Oakfield uses an SSH-driven atomic release deployment. Each deployment is
uploaded to a timestamped directory, prepared, migrated, and then made live
by switching the `current` symlink. The previous release remains available
for rollback, and the five newest releases are retained.

## Phase 0 local-equivalent proof

The local target uses PHP 8.2.x, SSH, Apache, cron, and a non-root deploy
user. Node runs only on the deployment computer to build assets; the server
uses no persistent Node or PHP worker. Its cron entry runs Laravel's
scheduler every minute, and the queue uses the `sync` driver. The
`deploy/local` Compose stack is available, but Phase 0 was actually proved
with the isolated Alpine WSL fallback after Docker image extraction failed
on this Windows host. Alpine is a stand-in, not a claim that cPanel runs
Alpine or that its plan-specific behavior has been verified.

For the WSL fallback, obtain and verify the official Alpine 3.19.9
minirootfs archive, import it as a project-scoped `oakfield-local` WSL 2
distribution, and install Apache, PHP 8.2 plus Laravel extensions,
MariaDB, OpenSSH, Bash, and cron. The exact setup script is
`deploy/local/setup-wsl.sh`; it derives the project path from its own
location, creates the test-only school database and key-only `oakfield`
account, and configures Apache on port 8080 and SSH on port 2222. It uses
the local Composer PHAR at `C:\ProgramData\ComposerSetup\bin\composer.phar`.
The throwaway key must be placed at `deploy/local/.local/id_ed25519` and
`id_ed25519.pub`. The `.local` directory is ignored by Git and never sent
in the deployment archive.

For this Windows workstation the archive was
`alpine-minirootfs-3.19.9-x86_64.tar.gz` from the official Alpine v3.19
release directory, SHA-256
`6b4444630d3c349edb99847da31591a91d529b4bf8235a4990d4cb2cab45b8e5`.
The distro was imported with `wsl --import oakfield-local <install-dir>
<verified-archive> --version 2`. Inside that distro, the package set is:

```sh
apk add --no-cache apache2 php82 php82-apache2 php82-bcmath php82-curl \
  php82-dom php82-fileinfo php82-mbstring php82-openssl php82-pdo \
  php82-pdo_mysql php82-session php82-simplexml php82-tokenizer php82-xml \
  php82-xmlreader php82-xmlwriter php82-zip php82-phar php82-ctype \
  php82-iconv php82-intl php82-pdo_sqlite php82-gd mariadb mariadb-client openssh \
  bash curl ca-certificates coreutils shadow
```

Run `setup-wsl.sh` as root via `wsl -d oakfield-local -u root --exec
/bin/sh <WSL-path-to-repo>/deploy/local/setup-wsl.sh`. Alpine's crontab
file must be root-owned for BusyBox cron to load the `oakfield` entry.

Run the normal preflight with the same local target arguments and
`-ValidateOnly` (without `-UseExistingBuild`), then deploy twice
from PowerShell with these local-target parameters:

```powershell
.\deploy\deploy.ps1 -HostName localhost -UserName oakfield `
  -RemotePath /home/oakfield/oakfield-sms -Port 2222 `
  -IdentityFile deploy/local/.local/id_ed25519 -UseExistingBuild
```

`-UseExistingBuild` is restricted to localhost and only reuses the Vite
manifest after the clean npm install, build, and tests have been verified;
it avoids repeated Windows `node_modules` file-lock failures while testing
the SSH release path. On a production host, omit this flag; preflight is
always required. Check `http://localhost:8080/up` for HTTP 200, run
`deploy/rollback.sh` over SSH against the previous known-good release,
then check `/up` and the active `current` symlink again. Verify PHP 8.2.x,
the minute-based cron entry, `QUEUE_CONNECTION=sync`, and file modes
(release code 755/644, shared `.env` 640, shared storage group-writable).
Record the observed results in `PROGRESS.md`.

This test must actually run before Phase 0 is complete. The production
account still needs its own go-live proof.

## One-time cPanel setup

The hosting account must provide PHP 8.2.x with GD enabled (needed for PNG
school logos in report-card PDFs), Composer 2, MySQL, SSH,
and a domain whose document root can be set to the application's
`current/public` directory.

1. Choose an application root outside `public_html`, for example
   `/home/CPANEL_USER/oakfield-sms`.
2. Create `/home/CPANEL_USER/oakfield-sms/shared/.env` from
   `.env.production.example`, fill in the real URL, database, mail, and
   `DEFAULT_SCHOOL_ID` values, and generate `APP_KEY` locally with
   `php artisan key:generate --show`.
3. Set the domain's cPanel document root to
   `/home/CPANEL_USER/oakfield-sms/current/public`.
4. Add the deployment computer's public SSH key to the cPanel account.
5. Configure cPanel cron to run `php /home/CPANEL_USER/oakfield-sms/current/artisan
   schedule:run` every minute. The initial configuration uses
   `QUEUE_CONNECTION=sync`; no persistent queue worker or Node server is
   required. If the purchased plan provides a different PHP CLI version,
   verify compatibility before changing the pinned 8.2.x release check.

The `.env` file is never uploaded by the deployment command. Application
storage is also shared between releases.

Release code uses standard shared-hosting 755/644 permissions so the web
user can read it. The private shared `.env` should be 640 and readable by
the effective PHP user through its group; shared storage should be
group-writable. Confirm those identities and modes on the purchased plan
during the go-live check.

## Deploy

From PowerShell at the repository root:

```powershell
.\deploy\deploy.ps1 `
  -HostName school.example.com `
  -UserName CPANEL_USER `
  -RemotePath /home/CPANEL_USER/oakfield-sms `
  -IdentityFile C:\path\to\id_ed25519
```

To exercise Composer validation, tests, the clean npm install, asset build,
and archive creation without contacting the server, add `-ValidateOnly`.

The command validates Composer metadata, runs the PHP tests, performs a clean
npm install and production asset build, uploads an application archive, runs
the database migrations, warms Laravel's caches, and switches the live
release. Any failure before the symlink switch leaves the current release
untouched.

After deployment, verify the trivial health endpoint:

```text
https://school.example.com/up
```

It must return HTTP 200 before the deferred live-host task can be marked
complete.

## Rollback

On the server, list `$APP_ROOT/releases`, choose a known-good release, and
run the provided rollback script:

```bash
bash /home/CPANEL_USER/oakfield-sms/current/deploy/rollback.sh /home/CPANEL_USER/oakfield-sms RELEASE_ID
```

Database migrations are not rolled back automatically. Review migration
compatibility before switching to older application code.
