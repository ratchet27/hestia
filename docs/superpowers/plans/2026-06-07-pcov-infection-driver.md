# pcov Infection Coverage Driver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `make mutate` collect Infection coverage under pcov instead of Xdebug — no "running with Xdebug enabled" notice, faster full pass, MSI unchanged.

**Architecture:** Install pcov in the `frankenphp_dev` Docker stage alongside the existing Xdebug, keep it dormant via `pcov.enabled = 0` in the dev INI (zero overhead on `make test`/CI), and flip it on only for Infection's initial coverage subprocess via `--initial-tests-php-options="-d pcov.enabled=1"`.

**Tech Stack:** FrankenPHP/PHP 8.4 dev image, `install-php-extensions`, Infection 0.32, Docker Compose, Make.

**Spec:** `docs/superpowers/specs/2026-06-07-pcov-infection-driver-design.md`

**Note on testing:** This is infrastructure/config (Dockerfile, INI, Makefile). There is no unit-testable surface; verification is behavioral — rebuild the dev image and observe `make mutate` output and MSI. Each task below ends with the concrete observation that confirms it.

---

## Files

- Modify: `backend/Dockerfile` — `frankenphp_dev` stage, add `pcov` to the xdebug `install-php-extensions` call.
- Modify: `backend/frankenphp/conf.d/20-app.dev.ini` — add dormant pcov config.
- Modify: `backend/Makefile` — `mutate` target enables pcov for the coverage subprocess.

---

### Task 1: Install pcov in the dev image

**Files:**
- Modify: `backend/Dockerfile` (the `frankenphp_dev` stage `install-php-extensions` block)

- [ ] **Step 1: Record the baseline**

Capture the current behavior so the improvement is provable. From `backend/`:

Run: `make up` (ensure the `php` container is running), then
Run: `docker compose exec php sh -c 'php -m | grep -i pcov || echo "pcov NOT loaded"'`
Expected: `pcov NOT loaded`

- [ ] **Step 2: Add pcov to the dev extension install**

In `backend/Dockerfile`, find this block in the `frankenphp_dev` stage:

```dockerfile
RUN set -eux; \
	install-php-extensions \
		xdebug \
	;
```

Change it to:

```dockerfile
RUN set -eux; \
	install-php-extensions \
		xdebug \
		pcov \
	;
```

Leave `frankenphp_base` and `frankenphp_prod` untouched — prod must not get pcov.

- [ ] **Step 3: Rebuild the dev image**

Run: `docker compose build php`
Expected: build succeeds; the `install-php-extensions` layer runs and reports pcov enabled.

- [ ] **Step 4: Recreate the container and verify pcov is loaded**

Run: `docker compose up -d php`
Run: `docker compose exec php sh -c 'php -m | grep -i pcov'`
Expected: prints `pcov`

Run: `docker compose exec php sh -c 'php -m | grep -i xdebug'`
Expected: prints `xdebug` (still present for step-debugging)

- [ ] **Step 5: Confirm pcov is dormant by default (no INI yet)**

Run: `docker compose exec php php -i | grep -E 'pcov.enabled|pcov.directory'`
Expected: `pcov.enabled => 1 => 1` (pcov defaults to enabled until Task 2 sets it to 0)

Note: at this point pcov is loaded *and* enabled-by-default. Task 2 makes it dormant. Do not run `make mutate` yet.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/Dockerfile
git commit -s -m "build(php): install pcov in dev image (#75)"
```

---

### Task 2: Keep pcov dormant by default

**Files:**
- Modify: `backend/frankenphp/conf.d/20-app.dev.ini`

- [ ] **Step 1: Append pcov config to the dev INI**

The file currently contains only Xdebug client-host config. Append these lines at the end of `backend/frankenphp/conf.d/20-app.dev.ini`:

```ini

; pcov is the Infection coverage driver (see Makefile `mutate`); kept dormant so
; plain `make test`/CI pay no coverage overhead. `pcov.enabled` is PHP_INI_SYSTEM,
; so `make mutate` flips it on per-run via `-d pcov.enabled=1`.
pcov.enabled = 0
pcov.directory = /app/src
```

This INI is bind-mounted into the container (`compose.override.yaml` mounts
`./frankenphp/conf.d/20-app.dev.ini` to `/usr/local/etc/php/app.conf.d/20-app.dev.ini:ro`),
so no rebuild is needed — just recreate/restart the container to reload PHP config.

- [ ] **Step 2: Restart the container to reload PHP config**

Run: `docker compose up -d php`
(If the mounted INI does not take effect, force it: `docker compose restart php`.)

- [ ] **Step 3: Verify pcov is now dormant**

Run: `docker compose exec php php -i | grep -E 'pcov.enabled|pcov.directory'`
Expected:
```
pcov.enabled => 0 => 0
pcov.directory => /app/src => /app/src
```

- [ ] **Step 4: Verify normal test path is unaffected**

Run: `make test`
Expected: suite passes (same as before — pcov dormant, no coverage instrumentation, no behavior change).

- [ ] **Step 5: Verify pcov can be force-enabled at process start**

This proves the per-run toggle mechanism works before wiring it into Infection:

Run: `docker compose exec php php -d pcov.enabled=1 -i | grep 'pcov.enabled'`
Expected: `pcov.enabled => 1 => 1`

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/frankenphp/conf.d/20-app.dev.ini
git commit -s -m "build(php): keep pcov dormant by default in dev (#75)"
```

---

### Task 3: Enable pcov only for Infection's coverage run

**Files:**
- Modify: `backend/Makefile` (the `mutate` target)

- [ ] **Step 1: Update the `mutate` target**

In `backend/Makefile`, change:

```make
mutate:
	docker compose exec php vendor/bin/infection --show-mutations
```

to:

```make
mutate:
	docker compose exec -e XDEBUG_MODE=coverage php vendor/bin/infection --show-mutations \
		--initial-tests-php-options="-d pcov.enabled=1"
```

**Why both flags** (discovered during execution — see the spec's superseded-assumption note):
- `--initial-tests-php-options="-d pcov.enabled=1"` enables pcov for the single initial
  coverage subprocess; php-code-coverage then selects pcov over Xdebug.
- `-e XDEBUG_MODE=coverage` suppresses the cosmetic `running with Xdebug enabled` notice.
  That notice keys on the Xdebug *extension being loaded* in Infection's own process, NOT
  on the coverage driver or `XDEBUG_MODE`. An active mode makes `composer/xdebug-handler`
  strip Xdebug from the process; it skips that strip when the mode is `off` (the container
  default) — which is why `off` alone leaves the notice in place.

- [ ] **Step 2: Run mutation testing and confirm pcov is the driver**

Run: `make mutate`
Expected:
- Notice reads `[notice] You are running Infection with PCOV enabled.` (NOT Xdebug).
- Infection completes; faster than the Xdebug-driven baseline (~5m13s measured) — expect
  ~4m40s. The win is modest (~11%): pcov only speeds initial coverage collection, not the
  dominant per-mutant test runs.

If the notice still says Xdebug, confirm `-e XDEBUG_MODE=coverage` reached `docker compose
exec` and that `INFECTION_ALLOW_XDEBUG` is not set (it tells the handler to keep Xdebug).

- [ ] **Step 3: Confirm MSI is unchanged and the suite is green**

From the same `make mutate` output (or `var/infection.log`):
Expected:
- MSI ≥ 90 (expect ~91% Covered, matching the floor in `infection.json5`).
- No failed Infection run / no error exit; floor not breached.

- [ ] **Step 4: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/Makefile
git commit -s -m "perf(test): run Infection coverage under pcov (#75)"
```

---

### Task 4: Final verification against acceptance criteria

**Files:** none (verification only)

- [ ] **Step 1: Re-run the full mutate pass from a clean container**

Run: `docker compose up -d php`
Run: `make mutate`
Expected (all of #75's acceptance criteria):
- No "running with Xdebug enabled" notice.
- MSI ≥ 90, suite green.
- Wall-clock measurably below the ~27 min Xdebug baseline. Record the observed time.

- [ ] **Step 2: Confirm the normal test path stayed fast/unchanged**

Run: `make test`
Expected: green, no coverage overhead (pcov dormant).

- [ ] **Step 3: Confirm prod image is untouched**

Run: `grep -n pcov backend/Dockerfile`
Expected: pcov appears ONLY inside the `frankenphp_dev` stage's `install-php-extensions`
block — not in `frankenphp_base` or `frankenphp_prod`.

- [ ] **Step 4: Backend lint gate**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: passes (no PHP source changed, but run the gate per project policy).
Note: `make lint` may auto-fix files — stage explicitly, never `git add -A`. Nothing
PHP changed here, so expect a clean tree.

---

## Out of scope

- General `make coverage` target / PHPUnit coverage reports.
- Any CI workflow change (CI does not run Infection).
- Removing Xdebug from the dev image.
