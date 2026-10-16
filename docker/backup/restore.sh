#!/bin/bash
# Restore drill / disaster recovery (docs/DEPLOY.md "Backups and restore").
#
#   restore.sh <backup file name or path> [target database]
#
# The file is taken from BACKUP_DIR or, if missing there, downloaded from BACKUP_RCLONE_REMOTE.
# The target database (default: <DB_DATABASE>_restore, a scratch copy) is dropped and recreated,
# so restoring into the live database must be asked for explicitly by name.
# Needs DB_ROOT_PASSWORD (to create the scratch database) or a user allowed to create it.
set -euo pipefail

: "${1:?usage: restore.sh <backup file> [target database]}" "${DB_HOST:?}" "${DB_DATABASE:?}" "${BACKUP_PASSPHRASE:?}"
dir="${BACKUP_DIR:-/backups}"
name="$(basename "$1")"
target="${2:-${DB_DATABASE}_restore}"
user="${RESTORE_DB_USERNAME:-root}"
password="${RESTORE_DB_PASSWORD:-${DB_ROOT_PASSWORD:-}}"

if [ -f "$1" ]; then
    src="$1"
elif [ -f "${dir}/${name}" ]; then
    src="${dir}/${name}"
elif [ -n "${BACKUP_RCLONE_REMOTE:-}" ]; then
    rclone copy "${BACKUP_RCLONE_REMOTE}/${name}" "$dir"
    src="${dir}/${name}"
else
    echo "restore: ${name} not found locally and no BACKUP_RCLONE_REMOTE" >&2
    exit 1
fi

if ! [[ "$target" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "restore: invalid database name ${target}" >&2
    exit 1
fi

client=(mariadb --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$user" --ssl --skip-ssl-verify-server-cert)

echo "restore: ${name} -> database ${target}"
MYSQL_PWD="$password" "${client[@]}" -e "DROP DATABASE IF EXISTS \`${target}\`; CREATE DATABASE \`${target}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSPHRASE -in "$src" \
    | gunzip \
    | MYSQL_PWD="$password" "${client[@]}" "$target"

tables="$(MYSQL_PWD="$password" "${client[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${target}'")"
echo "restore: done, ${tables} tables in ${target}"
