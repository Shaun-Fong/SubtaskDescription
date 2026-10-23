#!/usr/bin/env bash
#
# Validate the layout of a plugin archive built by `make all version=<x.y.z>`.
#
# Kanboard's plugin installer extracts the archive into the plugins/ directory
# and (for updates) uses the first zip entry as the plugin folder name, so the
# archive must contain exactly one top-level "Subtaskdescription/" directory.
#
# Usage: scripts/check-archive.sh <archive.zip>

set -euo pipefail

archive="${1:-}"

if [ -z "$archive" ]; then
    echo "usage: $0 <archive.zip>" >&2
    exit 2
fi

if [ ! -f "$archive" ]; then
    echo "::error::Archive not found: $archive" >&2
    exit 1
fi

entries="$(unzip -Z1 "$archive")"

printf '%s\n' "$entries"

top_level="$(printf '%s\n' "$entries" | cut -d/ -f1 | sort -u)"

if [ "$top_level" != "Subtaskdescription" ]; then
    echo "::error::Every archive entry must live under a top-level Subtaskdescription/ directory (found: $top_level)" >&2
    exit 1
fi

expected=(
    'Subtaskdescription/Plugin.php'
    'Subtaskdescription/Api/Procedure/SubtaskDescriptionProcedure.php'
    'Subtaskdescription/Schema/Mysql.php'
    'Subtaskdescription/Schema/Postgres.php'
    'Subtaskdescription/Schema/Sqlite.php'
    'Subtaskdescription/Template/subtask/form.php'
    'Subtaskdescription/Template/subtask/table_header.php'
    'Subtaskdescription/Template/subtask/table_rows.php'
)

for entry in "${expected[@]}"; do
    if ! printf '%s\n' "$entries" | grep -qxF "$entry"; then
        echo "::error::Missing archive entry: $entry" >&2
        exit 1
    fi
done

if printf '%s\n' "$entries" | grep -qE '^Subtaskdescription/(\.github|\.gitattributes|\.gitignore|Makefile|tests|scripts)(/|$)'; then
    echo "::error::Development files must be excluded from the archive" >&2
    exit 1
fi

echo "OK: $archive"
