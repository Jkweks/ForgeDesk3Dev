# Deploying the Field app from prebuilt images

Images are built by GitHub Actions (`.github/workflows/field-app-publish.yml`) whenever a release is
published, and pushed to GHCR:

- `ghcr.io/jkweks/forgedesk-field-app`
- `ghcr.io/jkweks/forgedesk-field-db`

Tags: `latest`, the full version (`1.2.3`) and `major.minor` (`1.2`).

## 1. Publish a release (dev machine)

Push the code, then on GitHub: **Releases -> Draft a new release**, create a tag such as `v1.2.3`, and
publish. Watch **Actions -> Build and Publish Field App Images**. You can also start it by hand from the
Actions tab (**Run workflow**), which publishes `latest`.

## 2. First-time setup on the field server

Needs Docker with Compose 2.23 or newer. Only two files are required, no source checkout:

```bash
mkdir -p ~/forgedesk-field && cd ~/forgedesk-field
# copy these two files here: docker-compose.prod.yml  and  .env.example (rename to .env)
cp .env.example .env
nano .env        # set POSTGRES_PASSWORD, FIELD_APP_DB_PASSWORD, FORGEDESK_SYNC_DB_PASSWORD, FIELD_DOMAIN
```

If the packages are private (GHCR default), log in once with a GitHub token that has `read:packages`
(or make the packages public under the package settings on GitHub):

```bash
echo <TOKEN> | docker login ghcr.io -u <github-user> --password-stdin
```

Start it:

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

## 3. Updating to a new release

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Compose recreates only the containers whose image changed; the database volume is kept. Phones pick up
the new app on their next load (the service worker is network-first).

To pin a version (or roll back), set `FIELD_IMAGE_TAG=1.2.3` in `.env` and run the same two commands.

## Checks

```bash
docker compose -f docker-compose.prod.yml ps          # app should show (healthy)
docker compose -f docker-compose.prod.yml logs -f app
```

## Notes

- The database upgrade scripts in `db/upgrades/` are not run automatically on existing data; apply new
  ones as described in `README.md` when a release adds one.
- To switch an existing server that was built from source: run the same commands in the same folder and
  project name (`forgedesk-field`); volumes are reused.
- Back up with `docker compose -f docker-compose.prod.yml exec -T db pg_dump -U fieldadmin fieldapp > fieldapp-$(date +%F).sql`.
