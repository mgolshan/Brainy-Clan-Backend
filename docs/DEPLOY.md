# Deploying Brainy Clan backend (production)

One Hetzner Cloud VPS runs everything with Docker Compose (`docker-compose.prod.yml`): **Caddy**
(automatic HTTPS) → **php-fpm** (`app`), **Horizon** (queues), **scheduler**, **MySQL 8.4**, **Redis**
and a **backup** container (nightly encrypted dump off-site). Budget: well under 200 CAD/month
(see Costs). Only ports 22, 80 and 443 are open; MySQL and Redis are never exposed.

Everything below was built and tested locally (production image boots, `php artisan about`, php-fpm
health, backup → restore round trip). The steps that need the real server (DNS, certificates,
Storage Box) are marked **(server)** and have not been run yet.

---

## 1. Hetzner account and server (server)

1. Create an account at <https://console.hetzner.cloud> and a project "Brainy Clan".
2. **SSH key** (on your PC, PowerShell): `ssh-keygen -t ed25519 -C "moe@brainyclan"` → upload the
   content of `~/.ssh/id_ed25519.pub` under *Security → SSH keys*.
3. **Add server**: location *Hillsboro (US-West)* or *Ashburn (US-East)* for North-American users
   (or Falkenstein/Helsinki — then update the privacy policy's "where data is stored"); image
   **Ubuntu 24.04 LTS**; type **CX22** (2 vCPU, 4 GB RAM, 40 GB disk) or the current shared-vCPU
   equivalent (in US locations the cheapest x86 type is CPX11/CPX21; pick ≥ 4 GB RAM); tick your SSH
   key; enable **Backups** (+20 %, whole-server snapshots, an extra safety net) and IPv6.
4. Note the IPv4 and IPv6 addresses.

## 2. Firewall and base system (server)

In the Hetzner console create a **Firewall** (inbound TCP 22, 80, 443 and UDP 443; everything else
dropped) and attach it to the server. Then on the server:

```sh
ssh root@<IPv4>
apt update && apt -y full-upgrade && apt -y install ufw unattended-upgrades fail2ban git
adduser --disabled-password --gecos "" deploy && usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
# SSH: keys only, no root login
sed -i 's/^#\?PasswordAuthentication .*/PasswordAuthentication no/; s/^#\?PermitRootLogin .*/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl restart ssh
ufw default deny incoming && ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp && ufw --force enable
dpkg-reconfigure -plow unattended-upgrades     # automatic security updates
```

Note: Docker publishes ports past ufw; that is fine here because only Caddy publishes ports.

## 3. Docker (server)

```sh
curl -fsSL https://get.docker.com | sh
usermod -aG docker deploy
```

Log in again as `deploy` (`ssh deploy@<IPv4>`) and check `docker compose version`.

## 4. DNS (server)

At the registrar of **brainyclan.app** add:

| Type | Name | Value |
| --- | --- | --- |
| A | api | `<IPv4>` |
| AAAA | api | `<IPv6>` |

Wait until `nslookup api.brainyclan.app` returns the server. Caddy requests the Let's Encrypt
certificate on first start (ports 80/443 must be reachable). `.app` domains are HTTPS-only (HSTS
preloaded), which Caddy handles.

## 5. Code and configuration (server)

```sh
sudo mkdir -p /srv/brainy-clan && sudo chown deploy: /srv/brainy-clan
git clone <repository URL> /srv/brainy-clan && cd /srv/brainy-clan
cp .env.prod.example .env.prod && chmod 600 .env.prod
nano .env.prod
```

**Production `.env.prod` checklist** — every `CHANGE_ME` replaced:

- [ ] `API_DOMAIN`, `APP_URL` (`https://api.brainyclan.app`), `ACME_EMAIL`
- [ ] `APP_KEY` — generate after the first build: `docker compose -f docker-compose.prod.yml --env-file .env.prod run --rm --no-deps --entrypoint php app artisan key:generate --show`
- [ ] `APP_ENV=production`, `APP_DEBUG=false` (the containers refuse to start with `APP_DEBUG=true`)
- [ ] `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REDIS_PASSWORD` — `openssl rand -base64 32` each
- [ ] Mail (SMTP provider) — needed for account deletion confirmation emails
- [ ] `LEGAL_*` company details; `LEGAL_DRAFT=true` until the lawyer review is done
- [ ] `SENTRY_LARAVEL_DSN` (section 10)
- [ ] Google / Facebook / Apple sign-in keys — README "Google and Facebook sign-in — setup" and "Sign in with Apple — setup"
- [ ] `REVENUECAT_WEBHOOK_SECRET`, `REVENUECAT_API_KEY`, `EXPO_ACCESS_TOKEN` — README "Subscriptions, rewarded ads and push — setup"
- [ ] `ANTHROPIC_API_KEY` — README "AI batch generator" (optional in production)
- [ ] `BACKUP_PASSPHRASE` + Storage Box `RCLONE_CONFIG_STORAGEBOX_*` (section 9). **Keep a copy of `BACKUP_PASSPHRASE` and `APP_KEY` in your password manager**: without them backups and encrypted columns (Apple tokens) cannot be restored.

## 6. First deploy (server)

```sh
cd /srv/brainy-clan
C="docker compose -f docker-compose.prod.yml --env-file .env.prod"
$C build                                   # app, web (Caddy) and backup images
$C up -d mysql redis                       # databases first
$C run --rm app php artisan migrate --force
$C run --rm app php artisan db:seed --force --class=PolicySeeder
$C run --rm app php artisan db:seed --force --class=CategorySeeder
$C run --rm app php artisan db:seed --force --class=ChallengeTypeSeeder
$C run --rm app php artisan db:seed --force --class=ContentSeeder
$C run --rm app php artisan db:seed --force --class=WordPuzzleSeeder
$C up -d                                   # caddy, app, horizon, scheduler, backup
$C ps                                      # every service "healthy" / "running"
```

Never run `DatabaseSeeder` or the demo seeders in production (demo users with known passwords).

**Admin user** (Filament at `https://api.brainyclan.app/admin`):

```sh
$C exec app php artisan tinker --execute '$u = new App\Modules\Identity\Models\User(["display_name" => "Moe", "email" => "you@example.com", "locale" => "en"]); $u->password = "a-long-unique-password"; $u->is_staff = true; $u->save();'
```

**Checks:**

```sh
curl -s https://api.brainyclan.app/api/v1/health      # {"data":{"status":"ok",...}}
curl -sI https://api.brainyclan.app/legal/privacy      # 200, strict security headers
$C exec horizon php artisan horizon:status              # "Horizon is running."
$C exec app php artisan schedule:list                   # goals, reminders, reconciliation
$C logs --tail=50 caddy app horizon scheduler backup
```

Then point the webhooks at the server: RevenueCat → `https://api.brainyclan.app/api/v1/webhooks/revenuecat`,
AdMob SSV → `https://api.brainyclan.app/api/v1/webhooks/admob-ssv`. Google Play's "account deletion
URL" is `https://api.brainyclan.app/account/delete`; the store privacy policy URL is
`https://api.brainyclan.app/legal/privacy`.

## 7. Updates and rollbacks (server)

```sh
cd /srv/brainy-clan && C="docker compose -f docker-compose.prod.yml --env-file .env.prod"
git fetch && git log --oneline HEAD..origin/main     # what is coming
docker tag brainy-clan-app:latest brainy-clan-app:previous && docker tag brainy-clan-web:latest brainy-clan-web:previous
git pull
$C build
$C run --rm app php artisan migrate --force          # backward-compatible migrations only
$C up -d                                             # recreates app, horizon, scheduler, caddy
$C exec horizon php artisan horizon:terminate        # (up -d already restarts it; this is a safety net)
```

Downtime is a few seconds while php-fpm is recreated. **Rollback**: `git checkout <previous commit>`,
then `APP_IMAGE_TAG=previous $C up -d` (images tagged above). A migration is only rolled back with
`php artisan migrate:rollback --step=1` if it has a safe `down()`; otherwise restore the backup taken
before the deploy (`$C run --rm backup backup.sh` right before risky migrations).

## 8. Monitoring

- **Errors:** Sentry (section 10). Personal data is scrubbed before sending (`SentryScrubber`).
- **Uptime:** a free external monitor (e.g. UptimeRobot or Better Stack) on
  `https://api.brainyclan.app/api/v1/health` every 5 minutes, alerting by email/SMS.
- **Queues:** Horizon dashboard `https://api.brainyclan.app/horizon` (staff only).
- **Server:** Hetzner console graphs (CPU, disk, network); `docker stats`; disk alert if `/` > 80 %.
- **Certificates:** renewed by Caddy automatically; Let's Encrypt mails `ACME_EMAIL` if something fails.

## 9. Backups and restore

The `backup` container runs `backup.sh` every night at 03:30 UTC (`BACKUP_CRON`):
`mariadb-dump --single-transaction` (consistent, no locks) → gzip → **AES-256 encryption** with
`BACKUP_PASSPHRASE` (openssl, PBKDF2) → upload with **rclone** to the off-site remote → delete
local and remote copies older than **14 days** (`BACKUP_RETENTION_DAYS`).

**Hetzner Storage Box (server):** order a BX11 (1 TB) in the Hetzner Robot console, enable SSH/SFTP
access, create a sub-account for backups, and put host/user into `RCLONE_CONFIG_STORAGEBOX_*`. The
password must be stored obscured: `$C run --rm backup rclone obscure 'the-password'` → paste the
output as `RCLONE_CONFIG_STORAGEBOX_PASS`. Check: `$C run --rm backup rclone lsd storagebox:`, then
`$C run --rm backup rclone mkdir storagebox:brainy-clan-backups`. (Any S3-compatible bucket works
too: set `RCLONE_CONFIG_<NAME>_TYPE=s3` and its keys.)

**Run a backup now:** `$C run --rm backup backup.sh`.

**Restore drill (do it once after setup, then every 3 months):**

```sh
$C run --rm backup rclone ls storagebox:brainy-clan-backups          # pick a file
$C run --rm backup restore.sh brainy_clan-20261016-033000.sql.gz.enc   # -> scratch DB brainy_clan_restore
$C exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT COUNT(*) FROM brainy_clan_restore.users; SELECT COUNT(*) FROM brainy_clan.users;"'
$C exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE brainy_clan_restore"'
```

**Real disaster recovery** (new server): sections 1–5, then `$C up -d mysql redis`, then
`$C run --rm backup restore.sh <file> brainy_clan` (replaces the live database — only on purpose),
then `$C up -d`. Users stay signed in (tokens are in the database) as long as `APP_KEY` is the same.

Tested locally (2026-10-16): backup of the dev database (84 KB encrypted) restored into a scratch
database with identical row counts (users, point_entries, content_items); a wrong passphrase fails
with "bad decrypt" and restores nothing.

## 10. Sentry

1. Create a free account at <https://sentry.io> (Developer plan: 5k errors/month), organization
   "Brainy Clan", project platform **Laravel**, data region of your choice.
2. In the project settings → *Security & Privacy*: enable **Data Scrubber** and **Use Default
   Scrubbers**, and turn off "Store client IP addresses".
3. Copy the DSN into `SENTRY_LARAVEL_DSN` in `.env.prod`, then `$C up -d` (containers re-cache config).
4. Test: `$C exec app php artisan sentry:test` → an event appears in Sentry.

What is sent: exception type, message and stack trace with emails, tokens and long random strings
masked; the request URL and method (tokens masked) and non-sensitive headers; the numeric user id
only. Never: request bodies, cookies, `Authorization`, names, nicknames, birth data, IPs.
Traces are sampled at 5 % (`SENTRY_TRACES_SAMPLE_RATE`). With an empty DSN Sentry is off.

## 11. Costs (estimate, October 2026)

| Item | Monthly |
| --- | --- |
| Hetzner CX22 / CPX21 VPS (+ IPv4) | ≈ 6–10 CAD |
| Hetzner server backups (+20 %) | ≈ 1–2 CAD |
| Hetzner Storage Box BX11 (1 TB) | ≈ 6 CAD |
| Domain brainyclan.app (≈ 20 CAD/year) | ≈ 2 CAD |
| SMTP provider (low volume) | 0–15 CAD |
| Sentry Developer, UptimeRobot free, Let's Encrypt | 0 CAD |
| **Total** | **≈ 15–35 CAD** — far below the 200 CAD/month budget |

Scale up later by resizing the server in the Hetzner console (a reboot, same disk/IP).

## Local test of the production image

```sh
# Behind a TLS-inspecting antivirus PECL downloads fail; reuse the dev image as the PHP base:
docker build -f docker/prod/Dockerfile --target prod --build-arg PHP_IMAGE=brainy-clan-backend-php -t brainy-clan-app:local .
docker run --rm --network brainy-clan-backend_default -e APP_ENV=production -e APP_KEY=base64:... \
  -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_DATABASE=brainy_clan -e DB_USERNAME=brainy -e DB_PASSWORD=secret \
  brainy-clan-app:local php artisan about
cp .env.prod.example .env.prod && docker compose -f docker-compose.prod.yml --env-file .env.prod config -q && rm .env.prod
```
