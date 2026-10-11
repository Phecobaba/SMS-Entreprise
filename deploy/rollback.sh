#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 2 ]; then
    echo "Usage: rollback.sh <absolute-app-root> <release-id>" >&2
    exit 64
fi

app_root="$1"
release_id="$2"

case "$app_root" in
    /*) ;;
    *) echo "The application root must be an absolute path." >&2; exit 64 ;;
esac

case "$release_id" in
    *[!0-9]*|'') echo "The release id must contain digits only." >&2; exit 64 ;;
esac

release_path="$app_root/releases/$release_id"
if [ ! -f "$release_path/artisan" ]; then
    echo "Release $release_id does not exist at $app_root." >&2
    exit 66
fi

ln -sfn "$release_path" "$app_root/current-next"
mv -Tf "$app_root/current-next" "$app_root/current"
echo "Release $release_id is active. Review database migration compatibility."
