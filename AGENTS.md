# Agent instructions

Project context, architecture rules and the Docker-only workflow for this repository live in
[CLAUDE.md](CLAUDE.md). Read it first. There is no PHP or Composer on the developer's Windows host:
run every PHP, Composer and Artisan command through `docker compose exec app ...`.
