#!/usr/bin/env bash
set -euo pipefail

# Only runtime code: never ship credentials, databases, storage or dependencies.
root=$(cd "$(dirname "$0")/.." && pwd)
destination=${1:?Usage: package-release.sh /absolute/path/release.tar.gz}
[[ $destination = /* ]] || { echo 'Archive destination must be absolute.' >&2; exit 1; }
cd "$root"
tar --exclude='bootstrap/cache' --exclude='public/storage' --exclude='public/hot' \
    -czf "$destination" app bootstrap config database/migrations database/seeders \
    database/factories public resources routes artisan cron-hourly.php composer.json composer.lock
sha256sum "$destination"
