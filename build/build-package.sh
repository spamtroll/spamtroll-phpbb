#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
project_root="$PWD"
version="$(php -r '$c=json_decode(file_get_contents("composer.json"),true); echo $c["version"];')"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
if [[ "${GITHUB_REF_TYPE:-}" == tag ]]; then
    [[ "${GITHUB_REF_NAME}" == "v${version}" ]]
fi
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
target="$staging/spamtroll/phpbb"
mkdir -p "$target" "$project_root/dist"
for path in acp adm config cron event language migrations service ext.php composer.json composer.lock README.md CHANGELOG.md PUBLICATION.md; do
    cp -R "$path" "$target/$path"
done
cp LICENSE "$target/license.txt"
composer install --working-dir="$target" --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins --optimize-autoloader --classmap-authoritative
test -f "$target/vendor/autoload.php"
test -f "$target/vendor/spamtroll/php-sdk/src/Client.php"
test -f "$target/vendor/spamtroll/php-sdk/LICENSE"
find "$target" -name '.DS_Store' -delete
archive="$project_root/dist/spamtroll_phpbb_${version}.zip"
rm -f "$archive"
(cd "$staging" && zip -qr "$archive" spamtroll)
php -r '$p=$argv[1]; file_put_contents($p.".sha256",hash_file("sha256",$p)."  ".basename($p)."\n");' "$archive"
echo "built $archive"
