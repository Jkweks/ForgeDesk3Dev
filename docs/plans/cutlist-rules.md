# Cut-list integrity rules + sill quarter-point settings (Development > Cut-list rules)

Decisions (2026-11-12): free-form rule builder; severity per rule (error blocks the import, warning is reported).

- **Where**: `/development#rules` tab, admin only. API `api/v1/cutlist-rules` (`admin` middleware): CRUD, `PUT/DELETE settings`, `POST test` (dry-run a CSV).
- **Tables**: `cutlist_rules` (kind row|count|companion, severity, enabled, definition JSON, message), `cutlist_rule_settings` (key => JSON override of `config/sill_quarter_points.php`).
- **Engine**: `App\Services\CutFlow\CutlistRuleEngine` — rows from `CutlistIngestService::parseCsv()`, grouped by job|work order|elevation; condition groups (all/any) over fields part_number, finish, use, tags, description, length, qty, bay, column, project, work_order, elevation.
- **Enforcement**: `CutFlow\ImportController::store` — error findings abort with a `csv` validation error; warnings are appended to `finish_warnings`.
- **Sill settings**: `SillQuarterPointSettings::effective()` = config + overrides (scalars, part_widths, row-name `roles`, `door_tags`). `SillQuarterPointService` reads them when constructed without explicit config.
- **Built-in structural checks** (one jamb pair, one SILL per bay, contiguous bays...) stay fixed and are listed read-only; the layout maths can't run without them.
- Tests: `tests/Feature/CutlistRuleTest.php`.
