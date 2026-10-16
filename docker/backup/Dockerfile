# Brainy Clan - nightly database backup container (docker-compose.prod.yml "backup").
# mariadb-dump (works with MySQL 8.4) -> gzip -> openssl AES-256 (passphrase from
# BACKUP_PASSPHRASE) -> rclone to an off-site remote (Hetzner Storage Box / S3), 14-day retention.
FROM debian:trixie-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates cron gzip mariadb-client openssl procps rclone tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY --chmod=0755 backup.sh restore.sh entrypoint.sh /usr/local/bin/

VOLUME /backups

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
