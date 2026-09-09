# Deployment

Hestia ships as **one Docker image on one host**. The image contains FrankenPHP
(Caddy + PHP), the Symfony API and the built React SPA. Caddy serves the SPA
and the API from the same origin, so the session cookie is first-party, there
is no CORS, and the Content-Security-Policy stays `default-src 'self'`.

This document covers what the repository provides and what a real deployment
still has to add. Nothing here is automated by CI yet; see
[Not automated](#not-automated-on-purpose-for-now).

## What the image contains

`backend/Dockerfile`, target `frankenphp_prod` (synced from
[symfony-docker](https://github.com/dunglas/symfony-docker), local changes are
marked `hestia:`):

| Stage | What it does |
| ----- | ------------ |
| `frankenphp_prod_builder` | `composer install --no-dev`, dumps autoloader and `.env` |
| `frontend_builder` | `oven/bun:1`, `bun install --frozen-lockfile`, `bun run build` with `VITE_API_BASE_URL=` (empty, so API calls are relative) |
| `frankenphp_prod` | `debian:13-slim`, runs as `www-data`, copies the PHP app and `frontend/dist` into `/app/public` |

The frontend source is passed as a **named build context** called `frontend`.
`compose.prod.yaml` declares it (`additional_contexts: frontend: ../frontend`);
a plain `docker build` needs `--build-context frontend=../frontend`.

Request routing in `backend/frankenphp/Caddyfile`:

| Path | Served by |
| ---- | --------- |
| an existing file under `/app/public` (`/assets/*.js`, favicons, `site.webmanifest`) | Caddy `file_server` |
| `/api/*`, `/_error/*` | Symfony (`index.php`) |
| anything else that is not a file (`/`, `/login`, `/stock`, ...) | `index.html` (SPA fallback) |

The image starts with `docker-entrypoint`, which waits for the database and
runs `doctrine:migrations:migrate` before serving.

## Build and run locally in prod mode

From `backend/`:

```bash
export APP_SECRET=$(openssl rand -hex 32) POSTGRES_PASSWORD=change-me RABBITMQ_PASSWORD=change-me
docker compose -f compose.yaml -f compose.prod.yaml build
docker compose -f compose.yaml -f compose.prod.yaml up -d
docker compose -f compose.yaml -f compose.prod.yaml exec php bin/console app:seed
echo "$PASSWORD" | docker compose -f compose.yaml -f compose.prod.yaml exec -T php bin/console app:user:create <username> --name "<name>" --password-stdin
```

`SERVER_NAME` defaults to `localhost`, so Caddy issues a self-signed
certificate; https://localhost shows the app. `app:seed --with-dev-user` is
refused outside `APP_ENV=dev`; create prod users with `app:user:create`.

If the dev stack is running on the same machine, publish prod on other ports:
`HTTP_PORT=8080 HTTPS_PORT=8443 HTTP3_PORT=8443` and use a separate compose
project name (`-p`), or the two stacks fight over ports 80/443 and volume names.

## Environment variables

Read by `compose.yaml` / `compose.prod.yaml`; `:?` means compose refuses to
start without it.

| Variable | Required | Meaning |
| -------- | -------- | ------- |
| `APP_SECRET` | yes | Symfony secret (CSRF, remember-me signatures). `openssl rand -hex 32`. |
| `POSTGRES_PASSWORD` | yes | Database password; the `database` service and `DATABASE_URL` share it. |
| `RABBITMQ_PASSWORD` | yes | Broker password for the Messenger transport. |
| `SERVER_NAME` | for a real host | Public hostname, e.g. `hestia.example.com`. A public name makes Caddy obtain a Let's Encrypt certificate automatically (ports 80 and 443 must be reachable). |
| `TRUSTED_PROXIES` | behind a proxy | Comma-separated proxy IPs or CIDRs when another reverse proxy sits in front of Caddy (`Symfony` then honours `X-Forwarded-*`). Leave empty when Caddy is the edge. |
| `TELEGRAM_DSN` | optional | Notifier transport for the daily expiry summary, `telegram://TOKEN@default?channel=CHATID`. If neither the shell nor compose's `.env` defines it, compose passes `null://null` and notifications are dropped. |
| `IMAGES_PREFIX` | optional | Registry prefix for the image name, e.g. `ghcr.io/ratchet27/`. |
| `HTTP_PORT`, `HTTPS_PORT`, `HTTP3_PORT` | optional | Published ports, default 80/443/443. |

Never put these in a committed file. On a server, a `.env` next to the compose
files (mode `600`) or the host's secret store is enough.

## Hosting options considered

Ranked for a single-household deployment.

1. **VPS + docker compose (recommended).** One small VM (2 vCPU, 2 GB is
   plenty). Copy `compose.yaml`, `compose.prod.yaml` and a `.env`, then
   `docker compose pull && docker compose up -d`. Caddy is the edge: TLS,
   HTTP/2, compression, no extra config. You own updates and backups.
2. **VPS + Coolify / Dokploy.** Same VM with a deploy UI, git-push deploys and
   built-in Postgres backups. Their proxy (Traefik) is the edge, so set
   `SERVER_NAME=:80` and `TRUSTED_PROXIES` to the proxy network.
3. **Fly.io / Railway.** Push the image, they run it. RabbitMQ and the
   worker cost extra, Postgres is a managed add-on; realistic cost is
   15 to 25 USD/month. Also needs `TRUSTED_PROXIES`.
4. **Split hosting** (SPA on a static CDN, API on a VPS). Not the current
   design; it would need CORS, `SameSite=None` cookies and a second origin in
   the CSP. Drop the `frontend_builder` stage and the SPA rewrite in the
   Caddyfile to go this way.

### Why one image

- No CORS, no cross-site cookie flags, strict CSP.
- One artifact, one version, one rollback (`docker compose pull` an older tag).
- TLS and static serving handled by Caddy with zero configuration.

Cost: a frontend-only change rebuilds the image (the `bun` stage is cached, so
this is about a minute), and static assets are served from the VPS rather than
a CDN. Both are irrelevant at household scale.

### CSP relaxations the SPA needs

`img-src data:` for inline SVG icons in the compiled CSS, and
`style-src 'unsafe-inline'` because `react-hot-toast` injects a `<style>`
element at runtime. Scripts, fetch targets and frames stay `'self'`. To go
fully strict, replace the toast library or add a per-request nonce (Caddy does
not generate one out of the box).

## Not automated on purpose (for now)

- **Image build in CI.** No workflow pushes `app-php-prod` to a registry. Until
  one exists, build on the server (`docker compose ... build`) or build locally
  and `docker save | ssh ... docker load`.
- **Database backups.** The `database_data` volume is the only copy. Add a
  nightly `pg_dump` (cron on the host, or a sidecar) before relying on it.
- **Monitoring.** Caddy's `/metrics` on port 2019 is used by the container
  healthcheck only; nothing exports it.
- **Renewal of the messenger worker.** `--time-limit=3600` plus
  `restart: unless-stopped` recycles it hourly; no supervisor beyond compose.
