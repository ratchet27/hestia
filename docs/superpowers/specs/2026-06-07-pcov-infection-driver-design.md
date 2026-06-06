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
	docker compose exec -e XDEBUG_MODE=coverage php vendor/bin/infection --show-mutations \
		--initial-tests-php-options="-d pcov.enabled=1"
```

Two mechanisms combine here:

1. **`--initial-tests-php-options="-d pcov.enabled=1"`** — Infection collects coverage
   by spawning a **single initial PHPUnit subprocess**. A `-d` flag on the parent
   `infection` process would **not** propagate to that subprocess; this is the supported
   hook that injects PHP options into it. Per-mutant runs do not collect coverage, so
   they need nothing extra. With pcov enabled there, php-code-coverage's driver selector
   picks **pcov over Xdebug** (verified: returns `PCOV 1.0.12`).

2. **`-e XDEBUG_MODE=coverage`** — needed only to silence the cosmetic
   `You are running Infection with Xdebug enabled.` notice. That notice is emitted by
   Infection purely on `extension_loaded('xdebug')` in its **own process**
   (`RunCommand.php:608`), independent of the actual coverage driver — so simply having
   Xdebug installed (which #75 requires, for step-debugging) triggers it. Infection's
   bundled `composer/xdebug-handler` restarts the process *without* Xdebug only when
   Xdebug is in an **active** mode; it deliberately skips the restart when
   `xdebug.mode=off`. Setting `XDEBUG_MODE=coverage` for the `make mutate` invocation
   thus makes the handler strip Xdebug from Infection's process entirely — the notice
   then correctly reads `running with PCOV enabled`, and Xdebug plays no part in the run.

> **Note — superseded assumption.** An earlier draft assumed `XDEBUG_MODE=off` (the
> container default, `Dockerfile:58` / `compose.override.yaml:19`) would suppress the
> notice. It does not: the notice keys on the extension being *loaded*, not its mode,
> and `off` is precisely the mode for which `composer/xdebug-handler` skips the strip.
> Hence the explicit `XDEBUG_MODE=coverage` override above.

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
   - notice reads `running with PCOV enabled` (not Xdebug),
   - MSI ≥ 90, suite green,
   - faster than the Xdebug-driven baseline.
3. `make test` unchanged — pcov dormant (`pcov.enabled = 0`), no behavioral change.

### Measured results (2026-06-07, back-to-back warm runs, 562 mutants)

| Run | Driver | Wall time | Notice | MSI |
|-----|--------|-----------|--------|-----|
| Xdebug baseline (`INFECTION_ALLOW_XDEBUG=1`, no pcov flag) | Xdebug | 5m13s | `with Xdebug enabled` | 92% |
| This change (`XDEBUG_MODE=coverage` + pcov flag) | PCOV | 4m39s | `with PCOV enabled` | 92% |

**~11% faster (34s), not a multiple.** pcov only accelerates Infection's *initial
coverage collection*; the dominant cost is re-running covering tests once per surviving
mutant (562 of them), which is identical across drivers. The win is real but modest;
the more visible benefit is the truthful notice and Xdebug being absent from the run.

## Out of scope

- A general `make coverage` target / PHPUnit coverage reports.
- Any CI workflow change (CI does not run Infection).
- Removing Xdebug from the dev image.
