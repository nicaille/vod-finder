#!/usr/bin/env bash
# Run inside a disposable container, never against an existing application.
set -euo pipefail
[[ -f /.dockerenv ]] || { echo 'This test requires a disposable Docker container.' >&2; exit 1; }
source_root=$(cd "$(dirname "$0")/../.." && pwd)
fixture=$(mktemp -d /tmp/ovh-test.XXXXXX)
project="$fixture/project"
payload="$fixture/payload"
mkdir -p "$project" "$payload" "$HOME/.vod-finder-deploy/incoming"
for directory in app bootstrap/cache config database/migrations database/seeders database/factories public resources routes; do
    mkdir -p "$project/$directory" "$payload/$directory"
done
mkdir -p "$project/vendor" "$project/storage/framework" "$project/storage/app/private"
printf 'secret-env\n' > "$project/.env"
printf 'private-key\n' > "$project/storage/app/private/webpush.json"
printf 'existing-vendor\n' > "$project/vendor/autoload.php"
printf 'local-composer\n' > "$project/composer.phar"
printf 'cache\n' > "$project/bootstrap/cache/config.php"
for file in artisan cron-hourly.php composer.json composer.lock public/.htaccess app/current.php; do
    printf 'old\n' > "$project/$file"
    printf 'new\n' > "$payload/$file"
done
printf 'custom\n' > "$project/public/custom.txt"
cat > "$fixture/php" <<'PHP'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> calls.txt
if [[ ${1:-} == artisan ]]; then
    case "$2" in
        down) touch storage/framework/down ;;
        up) rm -f storage/framework/down ;;
        migrate) [[ ! -f fail-migration ]] ;;
    esac
fi
PHP
chmod +x "$fixture/php"
commit=1111111111111111111111111111111111111111
archive="$fixture/release.tar.gz"
package() {
    # Production packaging has canonical paths without a leading ./.
    tar --exclude='bootstrap/cache' -czf "$archive" -C "$payload" app bootstrap config database public resources routes artisan composer.json composer.lock
    hash=$(sha256sum "$archive"); hash=${hash%% *}
}
deploy() { bash "$source_root/scripts/deploy-ovh.sh" "$archive" "$hash" "$commit" "$project" "$fixture/php"; }
assert_private() {
    [[ $(cat "$project/.env") == secret-env ]]
    [[ $(cat "$project/storage/app/private/webpush.json") == private-key ]]
    [[ $(cat "$project/vendor/autoload.php") == existing-vendor ]]
    [[ $(cat "$project/composer.phar") == local-composer ]]
    [[ $(cat "$project/public/custom.txt") == custom ]]
}
package
deploy
assert_private
[[ $(cat "$project/app/current.php") == new && ! -e $project/storage/framework/down ]]
[[ $(cat "$HOME/.vod-finder-deploy/current-commit.txt") == "$commit" ]]
cat > "$fixture/expected-calls.txt" <<'CALLS'
-v
composer.phar --version
artisan down --retry=60
composer.phar install --no-dev --optimize-autoloader --no-interaction --no-scripts
artisan config:clear
artisan clear-compiled
artisan package:discover --ansi
artisan migrate --force
artisan config:cache
artisan route:cache
artisan view:cache
artisan about
artisan up
CALLS
diff -u "$fixture/expected-calls.txt" "$project/calls.txt"
[[ $(find "$HOME/.vod-finder-deploy/backups" -type f | wc -l) == 1 ]]
if tar -tzf "$HOME"/.vod-finder-deploy/backups/*.tar.gz | grep -Eq '^(\.env|storage/|vendor/)'; then exit 1; fi
echo 'PASS: success, private files, code backup and commit marker'

# Previous managed files are removed, unknown user files are preserved.
rm "$payload/app/current.php"
printf 'next\n' > "$payload/app/replacement.php"
package
deploy
[[ ! -e $project/app/current.php && -f $project/app/replacement.php ]]
assert_private
echo 'PASS: obsolete managed files only'

package
touch "$project/fail-migration"
if deploy; then echo 'Expected migration failure'; exit 1; fi
[[ -e $project/storage/framework/down && ! -e $HOME/.vod-finder-deploy/lock ]]
[[ $(tail -n 1 "$project/calls.txt") == 'artisan migrate --force' ]]
assert_private
echo 'PASS: failed migration keeps maintenance and releases the lock'

# A retry cannot silently reopen a site already in maintenance.
package
if deploy; then exit 1; fi
rm "$project/fail-migration" "$project/storage/framework/down"
echo 'PASS: existing maintenance is respected'

hash=0000000000000000000000000000000000000000000000000000000000000000
if deploy; then exit 1; fi
[[ ! -e $project/storage/framework/down ]]
echo 'PASS: checksum failure leaves the running site untouched'

printf 'bad\n' > "$payload/.env"
tar -czf "$archive" -C "$payload" .env
hash=$(sha256sum "$archive"); hash=${hash%% *}
if deploy; then exit 1; fi
assert_private
echo 'PASS: forbidden private archive path rejected'

rm "$payload/.env"
ln -s /tmp "$payload/app/link"
package
if deploy; then exit 1; fi
rm "$payload/app/link"
echo 'PASS: archive symlinks rejected'

package
rm "$project/app/replacement.php"
ln -s /tmp/outside "$project/app/replacement.php"
if deploy; then exit 1; fi
rm "$project/app/replacement.php"
echo 'PASS: destination symlinks rejected'

mkdir "$HOME/.vod-finder-deploy/lock"
if deploy; then exit 1; fi
[[ -d $HOME/.vod-finder-deploy/lock ]]
rmdir "$HOME/.vod-finder-deploy/lock"
echo 'PASS: concurrent deployment cannot remove another process lock'
