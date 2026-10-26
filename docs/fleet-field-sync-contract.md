# Field app ⇄ ForgeDesk sync contract (v1)

`schema_version: 1`. Two codebases depend on this; change it deliberately.

## Principles

- **ForgeDesk is local-network only and always initiates.** It connects *out* to the field app's database (Laravel connection `fieldsync`, env `FIELDSYNC_*`, config `config/fleet.php`). Nothing public ever connects to the ForgeDesk database.
- The field app owns a **separate database** (separate host / DMZ). Do not share the ForgeDesk DB with it, even through a restricted role.
- The field app is **untrusted input**. ForgeDesk validates every submission; anything invalid is rejected or held for review in ForgeDesk (`field_sync_log`, Fleet → Field Inbox), never applied blindly.
- Sync is enabled with `FIELDSYNC_ENABLED=true`. Scheduler runs `fleet:sync-out` and `fleet:sync-in` every 2 minutes; both are no-ops when disabled and fail soft (logged, retried) if the field DB is unreachable.

## Field-side tables (PostgreSQL)

```sql
-- Published by ForgeDesk (read-only for the field app). Upserted by asset_number.
CREATE TABLE fa_assets (
  asset_number      text PRIMARY KEY,         -- also the QR payload on asset labels
  name              text NOT NULL,
  type_name         text NOT NULL,            -- joins to fa_checklists.type_name
  meter_type        text,                     -- odometer_miles | engine_hours | none
  status            text NOT NULL,            -- active | in_service_job | out_of_service | in_repair
  current_meter     numeric(12,1),
  current_job_label text,                     -- job name/number or free-text location
  is_active         boolean NOT NULL DEFAULT true,  -- false = hide (retired/sold); rows are never deleted
  synced_at         timestamp
);

-- Inspection checklists per asset type. Published by ForgeDesk. NOTE: no "critical" flag is
-- published; ForgeDesk decides criticality itself when ingesting.
CREATE TABLE fa_checklists (
  type_name     text PRIMARY KEY,
  template_name text NOT NULL,
  items         text NOT NULL,                -- JSON: [{"label": "Brakes"}, ...]
  updated_at    timestamp
);

-- Append-only inbox written by the field app, drained by ForgeDesk.
CREATE TABLE fa_submissions (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL UNIQUE,       -- client-generated; idempotency key (UUID v4)
  kind            text NOT NULL,              -- inspection | meter | defect
  asset_number    text NOT NULL,
  submitted_by    text,                       -- field user's display name/id (free text; shown in ForgeDesk)
  submitted_at    timestamp,                  -- device time (UTC). Clamped to "now" if >1 day ahead or older than 14 days
  payload         text NOT NULL,              -- JSON, see below
  ingested_at     timestamp                   -- set by ForgeDesk once handled (ingested, rejected or held)
);

-- Photos for a submission, as base64 text (ForgeDesk verifies real image type, re-encodes, stores privately).
CREATE TABLE fa_photos (
  id              bigserial PRIMARY KEY,
  submission_uuid text NOT NULL,
  filename        text NOT NULL,
  mime            text,                       -- informational only; ForgeDesk sniffs the bytes
  data_b64        text NOT NULL               -- JPEG/PNG/WebP, max 10 MB decoded, max 10 per submission
);
```

### Suggested database roles

| Role | Grants |
|---|---|
| `field_app` (public-facing app) | `SELECT` on `fa_assets`, `fa_checklists`; `INSERT` on `fa_submissions`, `fa_photos`; `SELECT` on own rows as needed. **No UPDATE/DELETE**. |
| `forgedesk_sync` (ForgeDesk) | `SELECT/INSERT/UPDATE` on `fa_assets`, `fa_checklists`; `SELECT/UPDATE(ingested_at)` on `fa_submissions`; `SELECT/DELETE` on `fa_photos`. |

Field-app logins/users live entirely in the field app. ForgeDesk only sees the `submitted_by` text.

## Payloads (`fa_submissions.payload`, version 1)

All strings are length-capped by ForgeDesk; unknown keys are ignored.

**`inspection`**
```json
{
  "template_name": "Truck inspection",
  "meter": 48210.0,
  "notes": "optional, ≤5000 chars",
  "items": [
    {"label": "Brakes", "result": "pass|fail|na", "comment": "optional, ≤1000 chars"}
  ]
}
```
`items[].label` should match a published checklist label. Criticality is looked up by label in ForgeDesk (client-sent `critical` is ignored). Any failure opens an `inspection_defect` work order and raises an in-app alert; a failed *critical* item makes the result `fail`, and if the asset type has "auto out-of-service" enabled the asset is set `out_of_service`.

**`meter`**
```json
{ "reading": 48250.0 }
```
A reading below the asset's current meter is *held for review* (Field Inbox), not applied.

**`defect`**
```json
{ "title": "Hydraulic leak", "description": "optional", "severity": "low|medium|high|critical" }
```
Opens a `repair` work order flagged as coming from the field app.

## ForgeDesk behaviour

1. **Out:** upserts `fa_assets` (in-service assets only; others flagged `is_active=false`) and `fa_checklists`. Nothing else leaves ForgeDesk: no costs, purchase info, VIN/plate, notes, or documents.
2. **In:** reads up to 100 rows with `ingested_at IS NULL`, ordered by `id`. For each: skip if `submission_uuid` already in `field_sync_log`; otherwise validate + apply inside a transaction; write a `field_sync_log` row (`ingested` | `rejected` | `needs_review`, with reason and original payload); set `ingested_at` on the remote row. Photos are pulled from `fa_photos`, verified by content sniffing (JPEG/PNG/WebP only), JPEG/PNG re-encoded to strip EXIF/GPS, stored on ForgeDesk's private disk, and removed from the field DB.
3. Replaying a submission (same `submission_uuid`) is always safe.
4. Dashboard shows a warning if the last successful pull/publish is older than 15 minutes.

## Field app responsibilities

- Queue submissions locally while offline and replay them with the **same `submission_uuid`**.
- Resolve a scanned QR code (`asset_number`) against `fa_assets`; show only `is_active` assets.
- Build the inspection form from `fa_checklists.items`.
- Never trust ForgeDesk-published data for authorization; manage your own users and sessions.
