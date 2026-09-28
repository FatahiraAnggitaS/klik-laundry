#!/usr/bin/env bash
set -euo pipefail

: "${RESTORE_SOURCE:?RESTORE_SOURCE is required}"
: "${RESTORE_DATABASE:?RESTORE_DATABASE is required}"
: "${RESTORE_CONFIRM:?RESTORE_CONFIRM is required}"
: "${AGE_IDENTITY_FILE:?AGE_IDENTITY_FILE is required}"
: "${RESTORE_EXPECTED_TABLE_COUNT:?RESTORE_EXPECTED_TABLE_COUNT is required}"
: "${RESTORE_EXPECTED_USER_COUNT:?RESTORE_EXPECTED_USER_COUNT is required}"
: "${RESTORE_EXPECTED_ORDER_COUNT:?RESTORE_EXPECTED_ORDER_COUNT is required}"

if [[ "$RESTORE_CONFIRM" != "isolated-restore" ]]; then
  echo "Set RESTORE_CONFIRM=isolated-restore after verifying the target is disposable." >&2
  exit 1
fi

if [[ "${APP_ENV:-}" == "production" ]]; then
  echo "Restore verification refuses production." >&2
  exit 1
fi

umask 077
tmp_dir="$(mktemp -d)"
trap 'rm -rf -- "$tmp_dir"' EXIT
dump_path="$tmp_dir/database.dump"

sha256sum --check "${RESTORE_SOURCE}.sha256"
age --decrypt --identity "$AGE_IDENTITY_FILE" --output "$dump_path" "$RESTORE_SOURCE"
pg_restore --exit-on-error --clean --if-exists --no-owner --no-acl --dbname="$RESTORE_DATABASE" "$dump_path"

table_count="$(psql --dbname="$RESTORE_DATABASE" --tuples-only --no-align --command="select count(*) from information_schema.tables where table_schema = 'public';")"
user_count="$(psql --dbname="$RESTORE_DATABASE" --tuples-only --no-align --command="select count(*) from users;")"
order_count="$(psql --dbname="$RESTORE_DATABASE" --tuples-only --no-align --command="select count(*) from orders;")"

if [[ "$table_count" -ne "$RESTORE_EXPECTED_TABLE_COUNT" || "$user_count" -ne "$RESTORE_EXPECTED_USER_COUNT" || "$order_count" -ne "$RESTORE_EXPECTED_ORDER_COUNT" ]]; then
  echo "Restore integrity verification failed." >&2
  exit 1
fi

echo "Restore verified in isolated database; record only counts and external evidence reference."
