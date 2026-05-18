# ForgeDeskDev — Fab Schedule Module

Extracted and web-ified from `zFAB_SCHEDULE.xlsm`.  
Designed to slot into **jkweks/forgedesk3dev**.

---

## Files

```
forgedesk_fab/
├── schema.sql          PostgreSQL schema (run once)
├── index.html          Single-page frontend (Manager + Shop Floor views)
└── api/
    ├── config.php      DB connection, shared helpers, CORS
    ├── work_orders.php REST endpoint — CRUD for work orders
    ├── stages.php      REST endpoint — CRUD for WO stages
    └── users.php       GET — user list for dropdowns
```

---

## Setup

### 1. Database

```bash
psql -U forgedesk -d forgedesk -f schema.sql
```

This creates:
- `fd_users` — workers and managers
- `fd_work_orders` — active jobs (mirrors Work Order List sheet)
- `fd_wo_stages` — per-job checklist stages (new feature)
- `fd_stage_templates` — default stages seeded for SF and CW types
- `fd_stage_log` — activity log per stage
- `fd_rate_constants` — the 4 rate values from rows 1–4 of the spreadsheet
- View: `fd_active_work_orders` — active WOs with effective hours

### 2. Environment variables

```bash
export PGHOST=localhost
export PGPORT=5432
export PGDATABASE=forgedesk
export PGUSER=forgedesk
export PGPASSWORD=yourpassword
```

Or set them in your PHP-FPM / Apache env config.

### 3. API location

The frontend expects the PHP API at `/fab/api/`. Change the `const API` line in `index.html` to match your route:

```js
const API = '/fab/api';  // → /fab/api/work_orders.php, etc.
```

### 4. Remove demo data

`index.html` contains `DEMO_WOS` and `DEMO_STAGES` arrays so it renders standalone without a server. Once your API is live, delete those arrays and uncomment the `fetch()` calls (marked with `// In production:`).

---

## Data model decisions

### Estimated hours formula (mirrors spreadsheet)

| Type | Formula |
|------|---------|
| SF | `(joints × 0.25) + (DR/FR × 1.5) + (doors × 2.25)` |
| CW | `(joints × 0.5)  + (DR/FR × 1.5) + (doors × 2.25)` |

The 4 rate constants live in `fd_rate_constants` and can be updated without a deploy.  
`estimated_hours_override` on a work order supersedes the calculation (shown as ★ in the UI).

### Stages

- On WO creation, `fd_stage_templates` for that job type are copied into `fd_wo_stages`
- Templates are seeded: SF gets 6 stages, CW gets 5
- Managers can add/remove/reorder stages per WO
- Stage status: `pending → in_progress → complete` (or `blocked`)
- Each status transition logs to `fd_stage_log`

### Archive

Archiving is soft-delete: `archived = TRUE`, `archived_at = NOW()`.  
Pass `?archived=1` to `work_orders.php` to list archived records.

---

## API reference

### Work Orders

| Method | Path | Description |
|--------|------|-------------|
| GET | `/work_orders.php` | List active WOs (filter: `?type=SF&pm=Dan+S&q=search`) |
| GET | `/work_orders.php?id=42` | Single WO with stages |
| POST | `/work_orders.php` | Create WO (auto-seeds stages) |
| PUT | `/work_orders.php?id=42` | Full update |
| PATCH | `/work_orders.php?id=42` | Partial update |
| DELETE | `/work_orders.php?id=42` | Soft archive |

### Stages

| Method | Path | Description |
|--------|------|-------------|
| GET | `/stages.php?wo_id=42` | All stages for a WO |
| POST | `/stages.php` | Add custom stage |
| PATCH | `/stages.php?id=5` | Update status/notes/assignee |
| DELETE | `/stages.php?id=5` | Remove stage |

---

## Importing the existing spreadsheet

A one-time import script is straightforward — the column mapping is:

| Column | DB field |
|--------|----------|
| PROJECT | `project_name` |
| Job # | `job_number` |
| WO# | `wo_number` |
| Date Received | `date_received` |
| Planned Start Date | `planned_start_date` |
| PLANNED COMPLETE DATE | `planned_complete_date` |
| Requested Finish Date | `requested_finish_date` |
| TYPE | `job_type` |
| System | `system` |
| Joints | `joints` |
| DR/FR Units | `dr_fr_units` |
| Doors Units | `doors_units` |
| Material Arrived | `material_arrived` |
| Cut List Glazer | `cut_list_glazer` |
| NOTES | `notes` |
| Project Manager | `project_manager` |

Dates in the spreadsheet are stored as Excel serial numbers (days since 1900-01-01).  
Conversion: `date('Y-m-d', ($serial - 25569) * 86400)` in PHP, or `=DATE(1900,1,1)+serial-2` in Excel.
