# Switch Infection coverage driver from Xdebug to pcov (#75)

**Date:** 2026-06-07
**Issue:** [#75](https://github.com/ratchet27/hestia/issues/75) — `perf(test): switch Infection coverage driver from Xdebug to pcov`
**Type:** tech-debt
**Scope:** backend dev image only

## Problem

`make mutate` runs Infection inside the long-lived `php` container, built from the
`frankenphp_dev` Dockerfile stage. That stage installs **xdebug** (`Dockerfile:65`)
and **pcov is not installed anywhere**. Infection forces `XDEBUG_MODE=coverage` in its
coverage subprocess, so every run:

- prints `[notice] You are running Infection with Xdebug enabled.`
- collects initial full-suite coverage (~335 tests, including heavy functional tests)
  under Xdebug, several times slower than necessary.

A full-scope `make mutate` took ~27 min across three runs while implementing #57.

CI does **not** run Infection (`backend-ci.yaml` runs only `bin/phpunit`, `coverage: none`),
so this is purely a local dev-image change.

## Goal

`make mutate` collects coverage under pcov instead of Xdebug:

- no "running with Xdebug enabled" notice during coverage,
- measurably reduced wall-clock for a full pass,
- MSI unchanged (floor 90, currently ~91), suite green,
- Xdebug stays installed for step-debugging.

## Design

### Decision summary

- **Enablement strategy:** toggle per-run — pcov installed but dormant
  (`pcov.enabled = 0`) so plain `make test` and CI's `bin/phpunit` pay zero coverage
  overhead. Coverage paths opt in explicitly.
- **Scope:** `make mutate` only. No `make coverage` target, no CI change, Xdebug kept.

### 1. Install pcov in the dev image

`Dockerfile`, `frankenphp_dev` stage — add `pcov` alongside the existing xdebug install
so both extensions coexist:

```dockerfile
RUN set -eux; \
	install-php-extensions \
		xdebug \
		pcov \
	;
```

pcov is **not** added to `frankenphp_base` or `frankenphp_prod` — the production image
stays lean and never runs Infection.

### 2. Keep pcov dormant by default

`frankenphp/conf.d/20-app.dev.ini` — append:

```ini
pcov.enabled = 0
pcov.directory = /app/src
```

- `pcov.enabled = 0` → no coverage instrumentation on `make test` / CI `bin/phpunit`.
- `pcov.directory = /app/src` → scopes instrumentation to app code, skipping `vendor/`.
- `pcov.enabled` is `PHP_INI_SYSTEM`, so it can only be set at process start via `-d`
  (not `ini_set`) — which is exactly the mechanism step 3 uses.

### 3. Enable pcov only for Infection's coverage run

`Makefile`, `mutate` target:

```make
mutate:
	docker compose exec php vendor/bin/infection --show-mutations \
		--initial-tests-php-options="-d pcov.enabled=1"
```

Infection collects coverage by spawning a **single initial PHPUnit subprocess**. A
`-d` flag on the parent `infection` process would **not** propagate to that subprocess;
`--initial-tests-php-options` is the supported hook that injects PHP options into it.
Per-mutant runs do not collect coverage, so they need nothing extra.

With pcov enabled in the coverage subprocess, php-code-coverage selects **pcov over
Xdebug** as the driver → no Xdebug notice, faster collection. `XDEBUG_MODE` already
defaults to `off` (`Dockerfile:58`, `compose.override.yaml:19`), so Xdebug contributes
no coverage path.

## Why this shape

- **Toggle-per-run (vs. always-on `pcov.enabled = 1`)** keeps the normal test/CI path
  free of any pcov compile/runtime overhead. pcov is active only during the one
  coverage collection that needs it.
- **`--initial-tests-php-options`** is the only mechanism that correctly reaches
  Infection's coverage subprocess; a parent-level `-d` wouldn't.
- **Xdebug retained** per the issue — step-debugging still works; it just stops being
  the coverage driver.

## Verification / acceptance criteria

1. Rebuild the dev image (`docker compose build php` or via `make up` rebuild).
2. `make mutate`:
   - no `You are running Infection with Xdebug enabled.` notice,
   - capture wall-clock; compare against the ~27 min baseline from #57,
   - MSI ≥ 90 (expect ~91), suite green.
3. `make test` unchanged — pcov dormant (`pcov.enabled = 0`), no behavioral change.

## Out of scope

- A general `make coverage` target / PHPUnit coverage reports.
- Any CI workflow change (CI does not run Infection).
- Removing Xdebug from the dev image.
