#!/usr/bin/env bash
set -euo pipefail
umask 027

if [ "$#" -ne 3 ]; then
    echo "Usage: remote-deploy.sh <absolute-app-root> <archive> <release-id>" >&2
    exit 64
fi

app_root="$1"
archive="$2"
release_id="$3"

case "$app_root" in
    /*) ;;
    *) echo "The application root must be an absolute path." >&2; exit 64 ;;
esac

case "$release_id" in
    *[!0-9]*) echo "The release id must contain digits only." >&2; exit 64 ;;
esac

release_path="$app_root/releases/$release_id"
shared_path="$app_root/shared"

if [ ! -f "$shared_path/.env" ]; then
    echo "Missing $shared_path/.env. Complete the documented one-time server setup first." >&2
    exit 78
fi

php_version="$(php -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')"
if [ "$php_version" != '8.2' ]; then
    echo "This deployment target must run the locally verified PHP 8.2.x; found $php_version." >&2
    exit 78
fi
if ! php -r 'exit(extension_loaded("gd") ? 0 : 1);'; then
    echo 'PHP GD is required to render branded PNG report cards. Enable it before deploying.' >&2
    exit 78
fi

mkdir -p "$release_path" "$shared_path/storage"
tar -xzf "$archive" -C "$release_path"

if [ -d "$release_path/storage" ] && [ -z "$(find "$shared_path/storage" -mindepth 1 -print -quit)" ]; then
    cp -R "$release_path/storage/." "$shared_path/storage/"
fi

rm -rf "$release_path/storage"
ln -s "$shared_path/storage" "$release_path/storage"
ln -s "$shared_path/.env" "$release_path/.env"

cd "$release_path"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
# Shared hosting commonly serves PHP as a different Unix identity from the
# SSH deploy user. Keep code world-readable, but do not follow the private
# shared .env or writable storage symlinks while setting release permissions.
find "$release_path" -type d -exec chmod 755 {} +
find "$release_path" -type f -exec chmod 644 {} +
php artisan migrate --force
php artisan optimize
php artisan storage:link --force

ln -sfn "$release_path" "$app_root/current-next"
mv -Tf "$app_root/current-next" "$app_root/current"

rm -f "$archive" "$0"

find "$app_root/releases" -mindepth 1 -maxdepth 1 -type d -print \
    | sort -r \
    | tail -n +6 \
    | while IFS= read -r old_release; do
        [ -n "$old_release" ] && rm -rf -- "$old_release"
    done

echo "Release $release_id is active at $app_root/current."
