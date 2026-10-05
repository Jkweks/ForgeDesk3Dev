# Reports registry — organizing the Reports page as it grows

## Problem
`resources/views/reports.blade.php` (~2,800 lines) holds every report: a hardcoded launcher button, a hidden
card, a `load*Report()` function and a `reportMap`/`switch` entry each. `ReportsController` and
`routes/api.php` follow the same pattern (one big controller, one route line per report). Each new report
touches 4+ places in 3 files, and the flat button grid doesn't scale past ~12 reports.

## Proposal
1. **Registry** — `config/reports.php`, one entry per report:
   `key, title, icon, category, permission (reports.view default), export (pdf/csv), view (partial), endpoint`.
   Categories: Inventory, Purchasing, Fabrication, Cut Station, Jobs.
2. **Launcher** — page renders the menu from the registry, grouped by category, with a search box and
   "recent/favourite" (localStorage). Permission-filtered server-side.
3. **One partial per report** — `resources/views/reports/{key}.blade.php` holds the card markup *and* its
   `<script>` (load function, render). The shell injects the active partial lazily (fetch/`@include` all but
   hidden) and calls a conventional `window.reports[key].load()`; replaces `reportMap` + `switch`.
4. **Deep links** — `/reports/{key}` (and `?job=…` params) so a report can be bookmarked/shared, e.g. from a
   job page's "Material usage" link.
5. **Controllers** — split `ReportsController` by domain (`Reports\InventoryReports`, `…\FabricationReports`,
   `…\CutStationReports`), routes registered by a loop over the registry or grouped files.
6. **Shared bits** — extract pagination/sort/search helpers (`reportPaginationState`, `formatCurrency`, CSV/PDF
   export buttons) into a shared `reports/_helpers` partial/JS file.

## Migration path (no big bang)
- Phase 1: build the registry + shell + partial loader; port the two newest reports (Joints Completed,
  Material Usage) as the pattern.
- Phase 2: port remaining reports one at a time; delete their code from `reports.blade.php` as they move.
- Phase 3: split controller/routes; remove `reportMap`/`switch`.
Behavior and URLs of existing API endpoints stay unchanged throughout.

## Open questions
- Keep Tabler-card-per-report, or add a saved-filters / scheduled-email layer later?
- Which categories do you want, and should Material Usage live under Cut Station or Jobs?
- Per-report permissions (e.g. pricing-sensitive reports) beyond `reports.view`?

## Status
- **Phase 1 done (2026-10-03):** `config/reports.php` registry; menu rendered from it (categories Inventory /
  Fabrication / Jobs, search box, `data-permission` per button); `/reports/{key}` deep links (404 for unknown
  keys); partial loader (`window.ReportModules[key].load`).
- **Phase 2 done (2026-10-03):** all 13 reports are partials in `resources/views/reports/partials/`; the old
  `reportMap`/`switch` is gone. `reports.blade.php` keeps only the shell plus shared code (pagination/sort/search
  state, generic `exportReport`/`exportReportPdf`, formatting helpers). Report-specific exports moved with
  their report.
- **Phase 3 done (2026-10-03):** `ReportsController` split into `Api\Reports\{Inventory,Fabrication,Job}ReportsController`
  (shared `Concerns\GeneratesCsv`, each with a `csvExport()`), plus `ReportExportController` for
  `GET /reports/export?type=`. The registry's `controller` / `data` / `export` / `csv` keys now drive the 27 API
  routes (same URIs and permissions as before). A new report = registry entry + partial + controller action.
- **Left over:** `InventoryReportsController` is still ~1,500 lines (could split into stock vs. statements); the
  shared page JS (pagination/sort/search state, generic `exportReport`/`exportReportPdf`, helpers) is still inline
  in `reports.blade.php`.
