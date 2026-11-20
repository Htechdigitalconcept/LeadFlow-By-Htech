#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/.." && pwd)
version=$(sed -n 's/^ \* Version: //p' "$root/htech-leadflow.php")
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
release=${1:-"$root/build"}
mkdir -p "$release"
release=$(cd "$release" && pwd)
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir "$stage/htech-leadflow"
for file in htech-leadflow.php uninstall.php readme.txt LICENSE src assets; do
  cp -r "$root/$file" "$stage/htech-leadflow/"
done
zipfile="$release/htech-leadflow-$version.zip"
rm -f "$zipfile"
(cd "$stage" && zip -Xqr "$zipfile" htech-leadflow)
unzip -tq "$zipfile"
if unzip -Z1 "$zipfile" | grep -Ev '^htech-leadflow/((assets|src)/([^/]+)?|htech-leadflow\.php|uninstall\.php|readme\.txt|LICENSE)?$'; then
  echo 'Unexpected package contents' >&2; exit 1
fi
(cd "$release" && sha256sum "$(basename "$zipfile")" > "$(basename "$zipfile").sha256")
echo "$zipfile"
