#!/bin/bash
#
# Pre-command hook to validate Bash commands for Hestia project
# Blocks common mistakes and enforces project conventions
#

set -euo pipefail

# Read JSON input from stdin
json_input=$(cat)

# Extract command and working directory
command=$(echo "$json_input" | jq -r '.tool_input.command // empty')
cwd=$(echo "$json_input" | jq -r '.cwd // empty')

# Exit early if no command
if [[ -z "$command" ]]; then
	exit 0
fi

# =============================================================================
# Rule 1: Block npm/yarn/pnpm in frontend - must use bun
# =============================================================================
if [[ "$command" =~ ^(npm|yarn|pnpm)[[:space:]] ]] || [[ "$command" =~ [[:space:]](npm|yarn|pnpm)[[:space:]] ]]; then
	if [[ "$cwd" == *"/frontend"* ]] || [[ "$command" == *"frontend"* ]]; then
		echo "BLOCKED: Use 'bun' instead of npm/yarn/pnpm in frontend." >&2
		echo "Example: bun run check, bun install, bun run test:run" >&2
		exit 2
	fi
fi

# =============================================================================
# Rule 2: Block mago inside Docker - it runs locally
# =============================================================================
if [[ "$command" =~ "docker".*"mago" ]] || [[ "$command" =~ "docker compose exec".*"mago" ]]; then
	echo "BLOCKED: mago runs locally, not inside Docker." >&2
	echo "Use: cd backend && mago format && mago lint" >&2
	exit 2
fi

# =============================================================================
# Rule 3: Warn about bun commands without being in frontend directory
# =============================================================================
if [[ "$command" =~ ^bun[[:space:]] ]] && [[ "$cwd" != *"/frontend"* ]] && [[ ! "$command" =~ "cd ".*"frontend" ]]; then
	echo "BLOCKED: bun commands must run from frontend directory." >&2
	echo "Use: cd /home/pavel/projects/hestia/frontend && $command" >&2
	exit 2
fi

# =============================================================================
# Rule 4: Block running php/composer directly on host
# =============================================================================
if [[ "$command" =~ ^(php|composer)[[:space:]] ]] && [[ ! "$command" =~ "docker" ]]; then
	if [[ "$cwd" == *"/backend"* ]] || [[ "$command" == *"backend"* ]]; then
		echo "BLOCKED: PHP/Composer must run inside Docker container." >&2
		echo "Use: docker compose exec php $command" >&2
		exit 2
	fi
fi

# All checks passed
exit 0
