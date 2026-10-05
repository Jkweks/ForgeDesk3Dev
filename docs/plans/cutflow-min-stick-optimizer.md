# CutFlow: fewest-sticks optimizer

Status: first version built 2026-10-04 behind a CutFlow setting (Optimizer: "Old Optimizer" /
"New Optimizer", default Old). Differences from this draft: plans are cached by pile contents
(`Cache::remember`) instead of a last-plan cache between builds; the `DropPlanner` tie-break is not
implemented yet; `StickYield::sticks()` now follows the same Optimizer setting. Still to do: compare old vs new on real
cut lists before switching the default.

## Goal

For each profile (part number + finish) across the selected cut lists, plan the cuts so the
job uses the **fewest stock lengths**. Today `CutPlanner` is a greedy heuristic
(first-fit-decreasing). It never goes short-first, but it can use more sticks than the minimum,
and the whole-pile estimate and the stick-by-stick build are not tied together.

## Current behaviour (what we are replacing)

- `CutPlanner::buildStick()` fills one stick at a time, largest piece first, taking every piece
  that still fits (kerf after the first piece).
- `CutPlanner::projectRemainingStandardSticks()` packs the whole pile with first-fit-decreasing
  at the profile's stock length (`stockLengthFor()`: product stock length when Drop Rack is on,
  else the 288" setting).
- The estimate and the sticks actually built are computed independently, so they can disagree,
  and neither looks for a better combination of pieces.
- Known miss: pieces 4,4,3,3,3,3 on 10" sticks use 3 sticks with first-fit-decreasing; the best
  answer is 2 (4+3+3 twice).

## Design

### 1. Model it as bin packing

Kerf is only lost *between* pieces, so a stick holds pieces iff `sum(len) + (n-1)*kerf <= L`.
That is the same as `sum(len + kerf) <= L + kerf`. So treat each piece as size `len + kerf` and
each stick as capacity `L + kerf`. This removes the "first piece has no kerf" special case from
the solver.

Work in integers (inches x 1000, or 1/16") to avoid float drift in the fit checks.
Group identical lengths: the solver works on `(length, qty)` rows, not one entry per piece.

### 2. New solver class

`App\Services\CutFlow\StickPacker` (pure PHP, no DB, easy to unit test).

```
pack(array $pieces /* [length => qty] */, float $stockLength, float $kerf, int $nodeLimit): PackResult
PackResult { sticks: list<list<length>>, count: int, lowerBound: int, optimal: bool }
```

Steps:
1. **Lower bound:** `ceil(sum(sizes) / capacity)`, plus the tighter "pieces over half a stick
   can't share" bound. If a plan hits the bound it is provably optimal, so stop.
2. **Seed:** first-fit-decreasing and best-fit-decreasing; keep the better.
3. **Improve:** if the seed is above the lower bound, run a bounded search for a plan with one
   fewer stick (branch and bound over distinct-length patterns, or a bin-elimination local
   search). Repeat until the bound is met or the budget is spent.
4. **Budget:** a **node count**, not wall-clock time, so results are deterministic (the same
   pile always gives the same plan). Start with about 200k nodes; tune against real lists.
5. **Over-length pieces** (longer than the stock length) are excluded from the pack and reported
   separately, as `projectRemainingStandardSticks()` does today.

Tie-break among plans with the same stick count (second priority only, never at the cost of a
stick): prefer the plan that leaves the **last stick's leftover as one long drop** and that
turns the fewest leftovers into scrap (offcut under Min Drop) for Drop Rack parts. Reuse
`DropPlanner` to score this.

### 3. Estimate and build use the same plan

- `projectRemainingStandardSticks()` calls `StickPacker::pack()` and returns the same shape as
  now. It adds `lowerBound` and `optimal` for the dashboard line (for example "5 sticks (minimum)"
  or "5 sticks, best found; minimum is 4").
- `buildStick()` takes its pieces from the plan instead of greedy filling:
  - **Standard length stick** (the length the plan was made for): take the plan's next stick,
    the one holding the longest remaining piece.
  - **Non-standard length** (operator keys in an offcut or drop): choose the subset that fills
    that stick best (subset-sum / knapsack, maximum material used), then re-plan the rest at the
    standard length. Filling a one-off stick as full as possible is the right goal there.
- **Staying consistent between builds:** pieces on a pending stick are already excluded from the
  pool, so each build re-plans what's left. The remaining plan can never need more sticks than
  before, but the solver could return a different arrangement. To avoid pieces being reshuffled
  between sticks, cache the last plan (keyed by a hash of the pool, profile and stock length).
  If the pool is exactly the old plan minus the stick just built, reuse the cached remainder.
  Otherwise re-solve (quantities changed, another job selected, a stick cancelled, a piece cut
  manually).

### 4. Out of scope (note, don't build yet)

- **Configurator `StickYield::sticks()`** (the stock-length page and job reservations) is a
  separate first-fit-decreasing and would also benefit from the shared solver. Changing it
  shifts reservation quantities on existing jobs when they regenerate, so it needs its own
  decision.
- **Reusing existing drops** from the rack as stock for new work.
- **Mitered ends** (`left_cut_angle` / `right_cut_angle`) sharing a cut to save kerf.

## Files

| File | Change |
|---|---|
| `app/Services/CutFlow/StickPacker.php` | new solver |
| `app/Services/CutFlow/CutPlanner.php` | `buildStick()` and `projectRemainingStandardSticks()` use it; plan cache |
| `app/Livewire/CutFlow/Dashboard.php` | pass `lowerBound` / `optimal` through |
| `resources/views/cutflow/livewire/dashboard.blade.php` | show "minimum" vs "best found" |
| `tests/Unit/StickPackerTest.php` | new |

No migration needed. The plan cache can use the Laravel cache store.

## Tests

- 4,4,3,3,3,3 on a 10" stick gives 2 sticks, with a zero-kerf case and a kerf case.
- Kerf equivalence: the transformed model gives the same fit result as the explicit
  `sum + (n-1)*kerf` check on random pieces.
- Never worse than first-fit-decreasing on random piles.
- Matches an exhaustive search on random piles of 10 pieces or fewer.
- Hits the lower bound when one exists (for example every piece fits a stick exactly).
- Large pile (about 500 pieces, 30 distinct lengths) finishes within the node budget and gives
  the same result on repeated runs.
- Over-length pieces are reported separately and not packed.
- Build sequence: building sticks one at a time from the plan uses the same total number of
  sticks as the up-front estimate.
- Non-standard stick length fills as full as possible and the rest still packs to the same or
  fewer sticks.
- `DropPlanner` tie-break only changes arrangement, never the stick count.

## Rollout

1. Build and test `StickPacker` alone.
2. Compare old vs new stick counts on real cut lists (a throwaway artisan command over the
   cutflow DB; dev currently has only one list, so also test against EZ Estimate data).
3. Switch the dashboard estimate to the new solver and show "minimum" / "best found".
4. Switch `buildStick()` to follow the plan. This is the change operators will notice, so do it
   last and check on the shop floor.

## Open questions

- Is a node budget of about 200k enough for the biggest real lists? Measure in step 2.
- When two plans use the same number of sticks, is "one long drop at the end" the right
  tie-break, or should it prefer the most racked drops?
- Should the shared solver also replace `StickYield::sticks()` (see Out of scope)?
