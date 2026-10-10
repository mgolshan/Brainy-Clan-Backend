#!/usr/bin/env bash
#
# Brainy Clan backend - one-time setup for WSL / Linux / macOS.
# (Windows PowerShell users: scripts/setup.ps1 does the same thing.)
#
#   bash scripts/setup.sh
#
# Builds the PHP image, starts MySQL/Redis/Mailpit, runs docker/scripts/bootstrap.sh in
# the app container (composer, .env, package installers, migrations, admin user), starts
# the full stack and finally runs the interactive Laravel Boost installer.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

MYSQL_WAIT_SECONDS=120

step() {
    printf '\n\033[1;36m==> %s\033[0m\n' "$*"
}

fail() {
    printf '\n\033[1;31mERROR: %s\033[0m\n' "$*" >&2
    exit 1
}

# Build the image with your uid/gid so files created in the container belong to you.
# (Docker Desktop on macOS maps ownership itself; this matters on Linux/WSL.)
HOST_UID="$(id -u)"
HOST_GID="$(id -g)"
if [ "$HOST_UID" = "0" ]; then
    HOST_UID=1000
    HOST_GID=1000
fi
export HOST_UID HOST_GID

step "Checking Docker"
command -v docker > /dev/null 2>&1 || fail "docker was not found. Install Docker Desktop (or Docker Engine) first."
docker info > /dev/null 2>&1 || fail "Docker is not running. Start Docker Desktop and run this script again."
docker compose version > /dev/null 2>&1 || fail "'docker compose' (Compose v2) is not available."

step "Building the PHP image"
docker compose build

step "Starting MySQL, Redis and Mailpit"
docker compose up -d mysql redis mailpit

step "Waiting for MySQL to become healthy (up to ${MYSQL_WAIT_SECONDS}s)"
mysql_container="$(docker compose ps -q mysql)"
[ -n "$mysql_container" ] || fail "The mysql container is not running."
waited=0
while true; do
    status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$mysql_container" 2> /dev/null || echo unknown)"
    if [ "$status" = "healthy" ]; then
        echo "    MySQL is healthy."
        break
    fi
    if [ "$waited" -ge "$MYSQL_WAIT_SECONDS" ]; then
        docker compose logs --tail 50 mysql || true
        fail "MySQL did not become healthy within ${MYSQL_WAIT_SECONDS}s (status: $status)."
    fi
    printf '    status: %s, waiting...\n' "$status"
    sleep 3
    waited=$((waited + 3))
done

step "Bootstrapping Laravel inside the app container"
docker compose run --rm app bash docker/scripts/bootstrap.sh

step "Starting the full stack (app, nginx, horizon, scheduler)"
docker compose --profile workers up -d

step "Laravel Boost installer (interactive)"
cat <<'EOF'
    Answer the prompts. When asked which agents/editors to configure, select
    "Claude Code" (and PhpStorm / Junie if it is offered and you use it).
    The MCP config it writes is replaced with a Docker-based one right afterwards.

EOF
docker compose exec app php artisan boost:install

step "Pointing the Boost MCP server (.mcp.json) at the Docker container"
docker compose exec -T app php docker/scripts/write-mcp-json.php

step "All done"
cat <<'EOF'

    API             http://localhost:8080          (health check: /up)
    Admin panel     http://localhost:8080/admin    admin@brainyclan.test / password
    Horizon         http://localhost:8080/horizon
    Mailpit         http://localhost:8025
    MySQL (host)    127.0.0.1:3307                 brainy / secret, database brainy_clan
    Redis (host)    127.0.0.1:6380

    Daily use:  docker compose --profile workers up -d    /    docker compose --profile workers down

EOF
