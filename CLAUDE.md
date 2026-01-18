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
