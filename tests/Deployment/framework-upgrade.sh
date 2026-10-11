#!/usr/bin/env bash
set -euo pipefail

baseline=${1:?Usage: framework-upgrade.sh /path/to/laravel10-checkout}
source_root=$(cd "$(dirname "$0")/../.." && pwd)
php=${VOD_TEST_PHP:-php}
composer_install() {
    if [[ -n ${VOD_TEST_COMPOSER_PHAR:-} ]]; then
        "$php" "$VOD_TEST_COMPOSER_PHAR" install "$@" --prefer-dist --no-interaction --no-progress
    else
        composer install "$@" --prefer-dist --no-interaction --no-progress
    fi
}
root=$(mktemp -d "${TMPDIR:-/tmp}/vod-upgrade-XXXXXX")
trap 'rm -rf "$root"' EXIT
for entry in app bootstrap config database public resources routes artisan composer.json composer.lock; do cp -a "$baseline/$entry" "$root/"; done
rm -rf "$root/bootstrap/cache"
mkdir -p "$root/bootstrap/cache" "$root/storage/app/private" "$root/storage/framework/cache/data" "$root/storage/framework/sessions" "$root/storage/framework/views" "$root/storage/logs"
# The caller must provide a newly created local MySQL database and test user.
# The PHP harness checks its host, name and username before any migration.
key=$("$php" -r 'echo base64_encode(random_bytes(32));')
cat > "$root/.env" <<EOF
APP_NAME="VOD Finder"
APP_ENV=production
APP_KEY=base64:$key
APP_DEBUG=false
APP_URL=http://localhost
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=${VOD_UPGRADE_DB_PORT:-3306}
DB_DATABASE=vod_finder_upgrade
DB_USERNAME=vod_test
DB_PASSWORD=isolated-test-only
DATABASE_URL=
CACHE_DRIVER=file
SESSION_DRIVER=file
MAIL_MAILER=array
QUEUE_CONNECTION=sync
BCRYPT_ROUNDS=4
EOF
export VOD_UPGRADE_ROOT="$root"
composer_install --working-dir="$root" --no-scripts
"$php" "$source_root/tests/Deployment/framework-upgrade.php" seed
bash "$source_root/scripts/package-release.sh" "$root/release.tar.gz"
tar -xzf "$root/release.tar.gz" -C "$root"
composer_install --working-dir="$root" --no-dev
"$php" "$source_root/tests/Deployment/framework-upgrade.php" verify
# New process checks the actual cached production bootstrap, without dev packages.
"$php" "$root/artisan" about
"$php" "$root/artisan" migrate --force
