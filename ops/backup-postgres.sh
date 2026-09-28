#!/usr/bin/env bash
set -euo pipefail

: "${BACKUP_DESTINATION:?BACKUP_DESTINATION is required}"
: "${AGE_RECIPIENT:?AGE_RECIPIENT is required}"
: "${PGDATABASE:?PGDATABASE is required}"

case "${APP_ENV:-}" in
  staging|production) ;;
  *) echo "Backup is only allowed in staging or production." >&2; exit 1 ;;
esac

umask 077
run_id="$(date -u +%Y%m%dT%H%M%SZ)"
tmp_dir="$(mktemp -d)"
trap 'rm -rf -- "$tmp_dir"' EXIT

dump_path="$tmp_dir/database.dump"
encrypted_name="klik-laundry-${run_id}.dump.age"
encrypted_path="${BACKUP_DESTINATION%/}/$encrypted_name"

mkdir -p -- "$BACKUP_DESTINATION"
pg_dump --format=custom --no-owner --no-acl --file="$dump_path"
age --recipient "$AGE_RECIPIENT" --output "$encrypted_path" "$dump_path"
sha256sum "$encrypted_path" > "${encrypted_path}.sha256"

echo "Encrypted backup created: $encrypted_name"
