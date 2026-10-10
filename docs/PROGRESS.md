# Brainy Clan Backend — Progress

Update this file at the end of every work session: milestone status, what changed, and what is next.
Milestones are defined in `docs/PLAN.md`.

## Status

| Milestone | Status | Notes |
| --- | --- | --- |
| Scaffold | Done | Laravel 13 skeleton, Docker stack, setup script, CLAUDE.md |
| B0 Local environment | Not started | Run `scripts\setup.ps1`; it has not been run yet |
| B1 Foundation | Not started | |
| B2 Identity, Family and invites | Not started | |
| B3 Content | Not started | |
| B4 First challenge types | Not started | |
| B5 Play and Scoring | Not started | |
| B6 Goals | Not started | |
| B7 Remaining launch content | Not started | |
| B8 Billing, ads and notifications | Not started | |
| B9 Production | Not started | |

## Next up

1. Run `powershell -ExecutionPolicy Bypass -File scripts\setup.ps1` and report any error.
2. Commit the pending changes (`.gitignore`, `CLAUDE.md`, `docs/`).
3. Start B1.

## Known issues and notes

- The setup script has only been checked statically; Composer resolution, the PHP 8.5 image build and the installers have not run yet.
- Boost has no PhpStorm option in `boost:install`; choose Claude Code. `.mcp.json` runs Boost through Docker.
- `User` has no `HasApiTokens` yet (planned in B1).
- `install:api` creates `/api/user`; versioned routes come in B1.
- MySQL is published on port 3307 to avoid a clash with Laragon.

## Log

### 2026-10-10
- Created the repository from the Laravel 13 skeleton with the Docker stack (PHP 8.5 FPM, nginx, MySQL 8.4, Redis, Horizon, scheduler, Mailpit) and the one-time setup scripts.
- Initial commit `7d3fcf5`. Added the design doc link to `CLAUDE.md`, plus `docs/PLAN.md` and `docs/PROGRESS.md` (not committed).
