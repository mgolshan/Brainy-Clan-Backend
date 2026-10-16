#!/bin/bash
# One backup run: consistent dump (InnoDB, --single-transaction, no table locks), gzip, encrypt with
# AES-256-CBC + PBKDF2 (BACKUP_PASSPHRASE), copy off-site with rclone, keep BACKUP_RETENTION_DAYS (14).
#
# Env: DB_HOST, DB_PORT (3306), DB_DATABASE, DB_USERNAME, DB_PASSWORD, BACKUP_PASSPHRASE,
#      BACKUP_RCLONE_REMOTE (e.g. "storagebox:brainy-clan"; empty = local only), BACKUP_RETENTION_DAYS,
#      BACKUP_DIR (/backups), RCLONE_CONFIG_* (rclone remote definition, see docs/DEPLOY.md).
set -euo pipefail

: "${DB_HOST:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}" "${BACKUP_PASSPHRASE:?}"
dir="${BACKUP_DIR:-/backups}"
days="${BACKUP_RETENTION_DAYS:-14}"
file="${dir}/${DB_DATABASE}-$(date -u +%Y%m%d-%H%M%S).sql.gz.enc"
tmp="${file}.partial"

mkdir -p "$dir"
trap 'rm -f "$tmp"' EXIT

echo "backup: dumping ${DB_DATABASE}@${DB_HOST}"
MYSQL_PWD="$DB_PASSWORD" mariadb-dump \
    --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
    --single-transaction --quick --no-tablespaces --triggers --hex-blob \
    --default-character-set=utf8mb4 --ssl --skip-ssl-verify-server-cert \
    "$DB_DATABASE" \
    | gzip -6 \
    | openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSPHRASE -out "$tmp"

mv "$tmp" "$file"
echo "backup: wrote $(basename "$file") ($(du -h "$file" | cut -f1))"

if [ -n "${BACKUP_RCLONE_REMOTE:-}" ]; then
    rclone copy "$file" "$BACKUP_RCLONE_REMOTE" --no-traverse
    rclone delete "$BACKUP_RCLONE_REMOTE" --min-age "${days}d" --include "${DB_DATABASE}-*.sql.gz.enc"
    echo "backup: uploaded to ${BACKUP_RCLONE_REMOTE}, pruned remote copies older than ${days} days"
else
    echo "backup: WARNING no BACKUP_RCLONE_REMOTE set, the backup is only on this server" >&2
fi

find "$dir" -name "${DB_DATABASE}-*.sql.gz.enc" -mtime "+$((days - 1))" -delete
echo "backup: done"
