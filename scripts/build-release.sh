#!/usr/bin/env bash
set -euo pipefail

version="${1:-0.9.0-beta.2}"
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
build_root="${repo_root}/build"
package_name="connect-cms-databaserelations-${version}"

rm -rf "${build_root}"
mkdir -p "${build_root}/${package_name}"

for source_dir in app database resources; do
    cp -a "${repo_root}/${source_dir}" "${build_root}/${package_name}/"
done

cp "${repo_root}/README.md" "${repo_root}/LICENSE" "${repo_root}/CHANGELOG.md" \
   "${repo_root}/SECURITY.md" "${build_root}/${package_name}/"

(
    cd "${build_root}/${package_name}"
    find . -type f -print | LC_ALL=C sort > MANIFEST.txt
)

if find "${build_root}" -type f \( -name '.env' -o -name '.env.*' -o \
    -name '*.log' -o -name '*.sql' -o -name '*.sqlite' -o \
    -name '*.sqlite3' -o -name '*.bak' \) -print | grep -q .; then
    echo "ERROR: prohibited file found" >&2
    exit 1
fi

(
    cd "${build_root}"
    zip -qr "${package_name}.zip" "${package_name}"
)

echo "${build_root}/${package_name}.zip"
