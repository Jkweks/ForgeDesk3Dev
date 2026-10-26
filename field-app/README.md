# ForgeDesk Field

A small installable mobile web app (PWA) for field crews: scan an asset's QR label, run an inspection,
update the meter, or report a problem, with photos. It works offline and sends when the phone has signal.

It is **separate from ForgeDesk**: its own code, its own Postgres, its own `docker-compose.yml`. It is
meant to run on its own (public-facing) server. ForgeDesk never exposes anything; it connects **out** to
this app's database to publish assets/checklists and collect submissions. Contract:
[`docs/fleet-field-sync-contract.md`](../docs/fleet-field-sync-contract.md).

```
 phones ──HTTPS──> proxy (Caddy) ──> app (Node) ──> db (Postgres) <──── ForgeDesk (connects out, every 2 min)
```

## Deploy

On the server (Docker + Compose v2 only):

```bash
cp .env.example .env        # set the three passwords and FIELD_DOMAIN
docker compose up -d --build
# then, in ForgeDesk: Users -> tick "Allow mobile app" and connect the sync (below)
```

Open `https://<FIELD_DOMAIN>` on a phone and use *Add to Home Screen*. Sign in with a ForgeDesk email and password.

| Setting | Meaning |
|---|---|
| `FIELD_DOMAIN` | Public hostname. A real DNS name gets a Let's Encrypt certificate automatically (ports 80/443 must reach this server). An IP or `localhost` gets a self-signed certificate (see below). `:80` = plain HTTP, local testing only. |
| `POSTGRES_PASSWORD` | Superuser, used only inside the db container. Never give it to ForgeDesk. |
| `FIELD_APP_DB_PASSWORD` | Role the app uses. |
| `FORGEDESK_SYNC_DB_PASSWORD` | Role ForgeDesk uses. |
| `FIELDSYNC_DB_BIND` / `FIELDSYNC_DB_PORT` | Where Postgres listens for ForgeDesk. Default `127.0.0.1:5432` (this machine only). |
| `HTTP_PORT` / `HTTPS_PORT` | Host ports for the proxy (default 80/443). |
| `COOKIE_SECURE` | Keep `true`. Only `false` for a plain-HTTP local test. |

> The role passwords are applied **only when the data volume is first created**. To change one later:
> `docker compose exec db psql -U fieldadmin -d fieldapp -c "ALTER ROLE field_app PASSWORD '...'"`, then update `.env` and `docker compose up -d`.

## Connecting ForgeDesk

In ForgeDesk's `.env`:

```
FIELDSYNC_ENABLED=true
FIELDSYNC_DB_HOST=<this server's address>
FIELDSYNC_DB_PORT=5432
FIELDSYNC_DB_DATABASE=fieldapp
FIELDSYNC_DB_USERNAME=forgedesk_sync
FIELDSYNC_DB_PASSWORD=<FORGEDESK_SYNC_DB_PASSWORD>
FIELDSYNC_DB_SSLMODE=require
```

Then, to connect it up:

1. Set `FIELDSYNC_DB_BIND` to an address ForgeDesk can reach (this server's LAN/VPN IP) and `docker compose up -d`.
2. **Firewall that port to ForgeDesk's IP only.** The database is the only door into this app's data. Prefer a VPN/WireGuard link or an SSH tunnel over exposing 5432 to the internet.
3. The database image serves TLS (self-signed), so `sslmode=require` encrypts the link. It does not authenticate the server, so the firewall rule still matters.
4. Check it from ForgeDesk: `php artisan fleet:sync-out` then `fleet:sync-in`.

The `forgedesk_sync` role can publish assets, checklists and crew logins, mark submissions ingested, and
read/delete photos. It cannot see sessions. The `field_app` role cannot update or delete submissions.

## Users (managed in ForgeDesk)

Crews sign in with their **ForgeDesk email and password**. There is no user management on this server:

1. In ForgeDesk, go to *Users*, open (or create) the person, and tick **Allow mobile app (field app) sign-in**.
   Only administrators can do this.
2. Within ~2 minutes ForgeDesk's `fleet:sync-out` publishes them here. Disabling the user, removing the tick,
   or changing their password in ForgeDesk carries over on the next sync, and existing sessions stop working at once.
3. A user still on a temporary password can't sign in to the app until they have set their own in ForgeDesk.

```bash
docker compose exec app node scripts/user.js list              # who can sign in, and when last synced
docker compose exec app node scripts/user.js signout me@x.com  # end someone's sessions immediately
```

> **Security trade-off:** because the password is the same, ForgeDesk's bcrypt hash for each opted-in user is
> stored in this (internet-facing) database. See the contract doc for the mitigations (blank unless the account
> can sign in, opt-in only, admin-only) and the advice to opt in least-privilege accounts only.

Display names in ForgeDesk's inbox come from the ForgeDesk user's name. Sessions last 30 days and renew while a phone is in use.

**Upgrading an existing install from the earlier local-users version:**
`docker compose exec -T db psql -U fieldadmin -d fieldapp -v ON_ERROR_STOP=1 < db/upgrades/002-forgedesk-users.sql`
(this drops the old local crew logins; re-create those people in ForgeDesk).

## Self-signed certificates (testing on a LAN / IP)

With an IP address or `localhost` as `FIELD_DOMAIN`, Caddy issues a certificate from its own CA. Phones will
warn once. To trust it properly, export the root and install it on the device:

```bash
docker compose cp proxy:/data/caddy/pki/authorities/local/root.crt ./caddy-root.crt
```

Camera/QR scanning requires HTTPS, so a real domain is the right answer for production.
Typing the asset number works everywhere.

## How it behaves

- **Offline:** assets and checklists are cached on the phone, and new work is saved to an outbox and
  retried with backoff. Each item carries a client-generated UUID reused on every retry, so a resend
  can never create a duplicate (here or in ForgeDesk).
- **Rejections:** if the server refuses an item (e.g. the asset was retired), it stays under *Sent* on
  the phone as "couldn't be sent" with the reason, until the user discards it.
- **Photos** are shrunk on the phone (max 1600px JPEG) before upload. The server also checks the real
  image type from the bytes; ForgeDesk re-checks and re-encodes them (stripping EXIF/GPS).
- **Identity** is taken from the login, never from the request body.
- **Updates:** `git pull && docker compose up -d --build`. The service worker is network-first, so
  phones pick up a new version the next time they're online.

## Backups

Everything lives in the `pgdata` volume. Unsent work sits on phones; ingested work lives in ForgeDesk, so
this database is short-lived by design, but back up the users:

```bash
docker compose exec -T db pg_dump -U fieldadmin fieldapp > fieldapp-$(date +%F).sql
```

## Development and tests

```bash
npm install                 # Node >= 20
npm test                    # unit tests; the API tests are skipped without a database
```

API/grant tests run against a real Postgres built from `db/init` (so the permissions are exercised too):

```bash
docker compose cp test app:/app/test
docker compose exec -e TEST_ADMIN_PASSWORD=<POSTGRES_PASSWORD> -e TEST_SYNC_PASSWORD=<FORGEDESK_SYNC_DB_PASSWORD> app node --test test/
```

**These tests truncate the field database (assets, users, submissions). Only run them on a separate test stack
(`docker compose -p fa-test ...`), never on a database holding real data.**

## Layout

```
docker-compose.yml  Caddyfile  Dockerfile  .env.example
db/        Dockerfile (Postgres + TLS), init/01-schema.sql (contract tables), init/02-roles.sh (grants), upgrades/
server/    app.js (security headers, static), routes.js (API), auth.js, validate.js, db.js
public/    the PWA: index.html, app.js, style.css, sw.js, manifest, icons
scripts/   user.js (list / sign out; logins come from ForgeDesk), make-icons.js
test/      validate.test.js (unit), api.test.js (integration + grants)
```
