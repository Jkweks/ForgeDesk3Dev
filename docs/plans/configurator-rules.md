# Configurator rules (Configurator Admin > Rules) + push / pull prep values

Built 2026-11-12. Decisions: rules are layered on top of the existing catalog (profile conditions,
default strike / cover and item backers are unchanged); a rule describes the exception.

## Rules

- **Where**: `/config/admin` > Rules tab. API `api/v1/config/rules` (view: `configurator.view`; write:
  `configurator.catalog.manage`): CRUD and `POST test` (dry run on an opening, nothing saved).
- **Table**: `configurator_rules` (name, notes, enabled, sort_order, conditions JSON, actions JSON).
- **Engine**: `App\Services\Configurator\ConfiguratorRuleEngine`; formulas by `RuleExpression`.
  `FrameBomGenerator`, `DoorBomGenerator` and `HwlibBomGenerator` call `apply()` as their last step.
  Unsaved configurations (parity check, older unit tests) are skipped.
- **Conditions** (all / any): hardware on the opening (by part number, category or library item;
  present or absent; optional leaf), a selected hardware function, an opening / frame / door field,
  or a comparison of two formulas.
- **Actions**: add / remove / swap / split / re-size a part in the frame, door or hardware BOM; set a
  hardware prep value; show a warning or an error (an error blocks release).
- **Formulas**: `W H TH THRESHOLD HAS_THRESHOLD HAS_TRANSOM IS_PAIR QTY`, any hardware variable code,
  `P2451.length` / `length('P2451')` (backer length, new field on backers), `section('Role')`, and in
  part actions `ORIGINAL`, `ORIGINAL_QTY`, `PIECE1…`, `REMAINDER`. Functions: min, max, abs, round, if.
- **Missing values never guess**: if a formula needs a value the opening does not have, that action
  is skipped and a warning is shown; the standard part stays.
- **Order of evaluation**: conditions and "set a prep value" formulas read the opening as it is before
  any rule runs; part actions then see the values rules set. An opening's own typed override of a
  prep value beats a rule (`HwlibResolver`, step 1b).
- **Attribution**: rule-made rows carry `rule_id` (all three parts tables) and show a "rule" badge.
  Hardware rows a rule adds are attached to the link that triggered the rule.
- **Stale BOMs**: generating a BOM stores a signature per scope in
  `door_frame_configurations.rule_signatures` (the rules that fired, their `updated_at`, and the values
  their formulas read). `GET door-frame-configurations/{id}/rule-status` compares it with the current
  one; the builder shows a "Generate / Recalculate" notice and release preflight blocks until redone.
- **Limits**: the editor handles a flat condition list (nested groups only via the API). A part a
  rule adds to the door BOM of a merged pair goes on the first leaf.

### Worked example (seeded by hand in dev, not by a migration)

When hardware with part number P2454 is on a single opening:
1. Add `P2451` (Electric Strike Backer), qty 1, to the hardware BOM.
2. Split `Lock Jamb Stop` into Lower = `PANIC_CL_AFF - THRESHOLD - P2451.length / 2` and
   Upper = `ORIGINAL - PIECE1 - P2451.length`.

Needs P2451's length (7) set under Hardware Library > Backers. Pairs carry the strike on the
removable mullion, so the rule is limited to singles.

## Push / pull prep values

- `configurator_hwlib_variables.push_pull` and `configurator_hwlib_items.push_pull` (`push`, `pull`,
  or empty). The migration tags the existing `PUSH_*` / `PULL_*` variables; `PUSH_PULL_*` stays both.
- `ConfiguratorHwlibItem::applicableVariables()` leaves out the other side's variables. Used by
  `HwlibResolver` (prep values, reports) and the prep-value save endpoint; the admin item and set
  editors filter the same way. An item with no side is asked for everything, as before.
- Items are not tagged automatically: set "Push or pull?" on each item in the Push-Pull category.
  Values already saved on the hidden side are kept, not deleted.

## Tests

`tests/Feature/ConfiguratorRuleTest.php`.
