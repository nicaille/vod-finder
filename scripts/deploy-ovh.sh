#!/usr/bin/env bash
set -euo pipefail
umask 022

archive=${1:?Usage: deploy-ovh.sh archive sha256 commit project php}
expected_hash=${2:?Missing SHA256}
commit=${3:?Missing commit}
project=${4:?Missing project directory}
php=${5:?Missing PHP executable}
[[ $commit =~ ^[a-f0-9]{40}$ && $expected_hash =~ ^[a-f0-9]{64}$ ]] || exit 1
case "$project" in '~/'*) project="$HOME/${project:2}" ;; esac
[[ $project = /* && $project != / && $project != "$HOME" ]] || exit 1
[[ -d $project && ! -L $project ]] || { echo 'Project directory unavailable.' >&2; exit 1; }
project=$(cd "$project" && pwd -P)
state="$HOME/.vod-finder-deploy"
mkdir -p "$state/backups" "$state/incoming"
chmod 700 "$state" "$state/backups" "$state/incoming"
mkdir "$state/lock" || { echo 'Another deployment is running; inspect the lock before retrying.' >&2; exit 1; }
stage=''
maintenance=0
finish() {
    local result=$?
    trap - EXIT
    [[ -z $stage ]] || rm -rf "$stage"
    rm -rf "$state/lock"
    if (( result != 0 && maintenance == 1 )); then
        echo 'Deployment failed: site kept in maintenance. Inspect the error, then retry; no database rollback was attempted.' >&2
    fi
    exit "$result"
}
trap finish EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

[[ -f $archive && -x $php ]] || { echo 'Archive or PHP executable missing.' >&2; exit 1; }
actual_hash=$(sha256sum "$archive")
[[ ${actual_hash%% *} = "$expected_hash" ]] || { echo 'Archive checksum mismatch.' >&2; exit 1; }
cd "$project"
for required in .env artisan vendor/autoload.php composer.phar; do
    [[ -f $required ]] || { echo "Required file missing: $required" >&2; exit 1; }
done
[[ ! -e storage/framework/down ]] || { echo 'Site already in maintenance: resolve the previous operation before retrying.' >&2; exit 1; }
for directory in app bootstrap config database public resources routes; do
    [[ ! -L $directory ]] || { echo "Code directory cannot be a symlink: $directory" >&2; exit 1; }
done
"$php" -v
"$php" composer.phar --version

# Reject archive paths outside the runtime allowlist before extraction.
safe_path() {
    local path=$1
    [[ $path != /* && $path != *'..'* && $path =~ ^[a-zA-Z0-9_./@+-]+$ ]] || return 1
    case "$path" in
        bootstrap/cache|bootstrap/cache/*|public/storage|public/storage/*|public/hot) return 1 ;;
        app|app/*|bootstrap|bootstrap/*|config|config/*|database|database/migrations|database/migrations/*|database/seeders|database/seeders/*|database/factories|database/factories/*|public|public/*|resources|resources/*|routes|routes/*|artisan|composer.json|composer.lock) return 0 ;;
        *) return 1 ;;
    esac
}
listing=$(tar -tzf "$archive")
while IFS= read -r entry; do
    safe_path "${entry%/}" || { echo "Forbidden archive path: $entry" >&2; exit 1; }
done <<< "$listing"
# Symlinks and hard links are never part of a release.
if tar -tvzf "$archive" | awk 'substr($0,1,1) != "-" && substr($0,1,1) != "d" {found=1} END {exit !found}'; then
    echo 'Archive contains unsupported links or special files.' >&2; exit 1
fi
stage=$(mktemp -d "$state/incoming/stage.XXXXXX")
tar --no-same-owner --no-same-permissions -xzf "$archive" -C "$stage"
find "$stage" -type f -printf '%P\n' | LC_ALL=C sort > "$state/lock/new-files.txt"
[[ -s $state/lock/new-files.txt && -f $stage/artisan && -f $stage/composer.lock ]] || exit 1
# A pre-existing nested symlink must not redirect a write outside the project.
while IFS= read -r file; do
    target="$project/$file"
    while [[ $target != "$project" ]]; do
        [[ ! -L $target ]] || { echo "Destination is a symlink: $file" >&2; exit 1; }
        target=${target%/*}
    done
done < "$state/lock/new-files.txt"

backup=$(mktemp "$state/backups/$(date -u +%Y%m%dT%H%M%SZ)-$commit.XXXXXX.tar.gz")
tar --exclude='bootstrap/cache' --exclude='public/storage' --exclude='public/hot' \
    -czf "$backup" app bootstrap config database/migrations database/seeders \
    database/factories public resources routes artisan composer.json composer.lock
chmod 600 "$backup"
"$php" artisan down --retry=60
maintenance=1
tar --no-same-owner --no-same-permissions -xzf "$archive" -C "$project"

# Delete only files owned by a previous automatic deployment, never user data.
if [[ -f $state/current-files.txt ]]; then
    LC_ALL=C comm -23 "$state/current-files.txt" "$state/lock/new-files.txt" > "$state/lock/obsolete.txt"
    while IFS= read -r file; do
        safe_path "$file" || exit 1
        target="$project/$file"
        while [[ $target != "$project" ]]; do
            [[ ! -L $target ]] || exit 1
            target=${target%/*}
        done
        rm -f -- "$project/$file"
    done < "$state/lock/obsolete.txt"
fi
# Install the new autoloader before booting new application classes.
"$php" composer.phar install --no-dev --optimize-autoloader --no-interaction --no-scripts
"$php" artisan config:clear
"$php" artisan clear-compiled
"$php" artisan package:discover --ansi
"$php" artisan migrate --force
"$php" artisan config:cache
"$php" artisan route:cache
"$php" artisan view:cache
"$php" artisan about
cp "$state/lock/new-files.txt" "$state/current-files.txt"
"$php" artisan up
maintenance=0
printf '%s\n' "$commit" > "$state/current-commit.txt"
rm -f -- "$archive"
echo "Deployed $commit. Code backup: $backup"
