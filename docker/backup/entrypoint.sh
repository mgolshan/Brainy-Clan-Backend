#!/bin/bash
# Installs the cron line (BACKUP_CRON, default 03:30 UTC daily) and runs cron in the foreground.
# Any other command is executed as-is, e.g. `docker compose run --rm backup backup.sh`.
set -euo pipefail

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

: "${BACKUP_PASSPHRASE:?BACKUP_PASSPHRASE is required}"

# cron starts jobs with an empty environment: hand the settings over in a file only root can read.
umask 077
export -p | grep -E '^declare -x (DB_|BACKUP_|RCLONE_|TZ=)' > /etc/backup.env

echo "${BACKUP_CRON:-30 3 * * *} root . /etc/backup.env && /usr/local/bin/backup.sh > /proc/1/fd/1 2>&1" > /etc/cron.d/brainy-backup
chmod 0644 /etc/cron.d/brainy-backup
echo "backup: scheduled '${BACKUP_CRON:-30 3 * * *}' (time zone ${TZ:-UTC}), remote '${BACKUP_RCLONE_REMOTE:-<none>}'"

exec cron -f
