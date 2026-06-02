# Hestia - Claude Code Instructions

## GitHub

Use `ratchet27` account for this repo:

```bash
gh auth switch -u ratchet27
```

## Git Commits

Use conventional commits format with signoff:

```bash
git commit -s -m "<type>(<scope>): <description>"
```

Or use the alias:

```bash
git ci -m "<type>(<scope>): <description>"
```

Use the `conventional-commits` skill for format reference.

GPG signing is configured automatically for this repo.

## Project Structure

- **Backend**: See `backend/AGENTS.md` for backend-specific instructions
- **Frontend**: See `frontend/AGENTS.md` for frontend-specific instructions

## Quick Commands

Always run from the correct directory. Use full paths to avoid mistakes.

| Task | Command |
|------|---------|
| Frontend check | `cd /home/pavel/projects/personal/hestia/frontend && bun run check` |
| Frontend test | `cd /home/pavel/projects/personal/hestia/frontend && bun run test:run` |
| Backend check | `cd /home/pavel/projects/personal/hestia/backend && make lint` |
| Backend test | `cd /home/pavel/projects/personal/hestia/backend && make test` |
| Regenerate API | `cd /home/pavel/projects/personal/hestia/frontend && NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api` |

### Common Mistakes to Avoid

- **NEVER** use `npm` in frontend → use `bun`
- **NEVER** run `mago` inside Docker → it runs locally on host
- **NEVER** run `bun run check` without `cd frontend` first
- **NEVER** run `php` or `composer` on host → use `docker compose exec php`
- **NEVER** use a subset (e.g. bare `mago format && mago lint`) as the backend gate → run **`make lint`**. It runs `rector → mago format → mago lint → mago analyze → phpstan`, a superset of CI's Code Quality job. Bare `mago lint` skips `mago analyze`, so analyzer errors (`invalid-return-statement`, `mixed-argument`, …) pass locally but fail CI.
- **`config/reference.php` is generated** (Symfony IDE-autocomplete dump, non-deterministic union ordering) and **gitignored** — never commit it or treat its churn as a real change.
