#!/usr/bin/env bash
#
# Builds the installable Joomla package: dist/pkg_kurso-<version>.zip
#
#   bin/build.sh            # version from src/pkg_kurso/pkg_kurso.xml
#
# Requires zip and either a local composer or Docker (composer:2 image).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/src"
DIST="$ROOT/dist"
VERSION="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$SRC/pkg_kurso/pkg_kurso.xml" | head -n1)"

if [[ -z "$VERSION" ]]; then
    echo "Cannot read the version from src/pkg_kurso/pkg_kurso.xml" >&2
    exit 1
fi

# All manifests must carry the same version.
for manifest in "$SRC"/com_kurso/kurso.xml "$SRC"/mod_kurso/mod_kurso.xml "$SRC"/plg_*/kurso.xml; do
    if ! grep -q "<version>$VERSION</version>" "$manifest"; then
        echo "Version mismatch: $manifest is not $VERSION" >&2
        exit 1
    fi
done

if ! grep -q "VERSION = '$VERSION'" "$SRC/com_kurso/administrator/src/Extension/KursoComponent.php"; then
    echo "Version mismatch: KursoComponent::VERSION is not $VERSION" >&2
    exit 1
fi

echo "Building KURSO for Joomla $VERSION"

# 1. Twig (production dependencies only)
if command -v composer >/dev/null 2>&1; then
    (cd "$ROOT" && composer install --no-dev --no-interaction --quiet)
else
    docker run --rm -u "$(id -u):$(id -g)" -v "$ROOT":/app -w /app -e COMPOSER_HOME=/tmp/composer \
        composer:2 install --no-dev --no-interaction --quiet
fi

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

PKG="$BUILD/pkg"
mkdir -p "$PKG"

# Zips the content of a directory with stable ordering and without extra file attributes.
make_zip() {
    local source_dir="$1" target="$2"
    (cd "$source_dir" && find . -type f ! -name '.DS_Store' | LC_ALL=C sort | zip -q -X -@ "$target")
}

# 2. Component: copy, then reduce the vendor folder to the Twig sources
cp -a "$SRC/com_kurso" "$BUILD/com_kurso"
VENDOR="$BUILD/com_kurso/administrator/vendor"
find "$VENDOR" -mindepth 1 -maxdepth 1 ! -name twig -exec rm -rf {} +
find "$VENDOR/twig/twig" -mindepth 1 -maxdepth 1 ! -name src ! -name LICENSE -exec rm -rf {} +
printf '# Library code, never accessed directly.\nRequire all denied\n' > "$VENDOR/.htaccess"
cp "$ROOT/LICENSE" "$BUILD/com_kurso/LICENSE"
make_zip "$BUILD/com_kurso" "$PKG/com_kurso.zip"

# 3. Module and plugins
for ext in mod_kurso plg_content_kurso plg_task_kurso plg_editors-xtd_kurso; do
    make_zip "$SRC/$ext" "$PKG/$ext.zip"
done

# 4. Package
cp "$SRC/pkg_kurso/pkg_kurso.xml" "$SRC/pkg_kurso/script.php" "$ROOT/LICENSE" "$PKG/"
cp -a "$SRC/pkg_kurso/language" "$PKG/language"

mkdir -p "$DIST"
rm -f "$DIST/pkg_kurso-$VERSION.zip"
make_zip "$PKG" "$DIST/pkg_kurso-$VERSION.zip"

echo "Created dist/pkg_kurso-$VERSION.zip ($(du -h "$DIST/pkg_kurso-$VERSION.zip" | cut -f1))"
