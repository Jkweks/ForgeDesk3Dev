# Pull Request Guardrails: A Plain-English Guide

This explains what automatic checks we want on every pull request (PR), why, and how to work with them. You don't need to be a CI expert. If you can push a branch, you can use all of this.

---

## The big idea

A **pull request** is you saying "here's my change, please merge it into `develop`."

Before it merges, we want a robot to:

1. Run our tests.
2. Check the code is tidy.
3. Make sure nothing obviously breaks.

If the robot says **red ❌**, the PR can't merge. If it says **green ✅** and a human approves, it can.

This matters more now that two people are working in the same codebase. Without it, one person's change can quietly break the other's, and we only find out in production.

**Where we are today:** we have *no* automatic checks on PRs. The only GitHub workflows we have build Docker images. So step one is adding them.

---

## Glossary (the words you'll see)

| Word | What it means |
|------|---------------|
| **CI** | "Continuous Integration." A robot that runs checks every time you push. |
| **Workflow** | A file in `.github/workflows/` that tells GitHub what checks to run. |
| **Required check** | A check that *must* be green before the merge button works. |
| **Branch protection / ruleset** | GitHub settings that enforce the rules (no pushing straight to `develop`, checks required, etc.). |
| **CODEOWNERS** | A file that says "this person must review changes to these files." |
| **Migration** | A file that changes the database structure. Two people making them at once is the #1 merge headache. |
| **Merge conflict** | Two people edited the same lines. Git can't pick a winner, so a human has to. |

---

## Part 1: The automatic checks

Each of these runs on every PR. Together they are the "CI" workflow.

### Must-have checks

| Check | What it does | Why we care |
|-------|--------------|-------------|
| **PHP tests** | Runs the Laravel test suite | Catches broken backend logic |
| **Security route test** (`SecurityHardeningTest`) | Fails if a new API route that changes data has no `permission:` protection | Stops anyone from accidentally shipping an unprotected endpoint. Great safety net for a new developer. |
| **JS smoke tests** (`laravel/tests/js-smoke`) | Loads each page's JavaScript in a fake browser | Our pages have JavaScript inside the Blade files. PHP tests can't see errors in it. This can. |
| **pm-worker tests** | Runs the Python tests for the PM drawing/estimate parser | Only runs when `pm-worker/` changed |
| **Code style (Pint)** | Checks formatting | Stops "reformatted the whole file" noise that causes merge conflicts |
| **Frontend build** (`npm run build`) | Builds the CSS/JS | A broken build otherwise only shows up when we deploy |
| **Migration check** | Runs every migration from scratch on a real Postgres database | Production uses Postgres. Our tests use SQLite, which hides some problems. |

### Important details

- **Tests run in CI, never on a live database.** Our tests use a throwaway in-memory database. Running tests carelessly against the dev database wiped it once. CI is a clean room, so it's safe.
- **Tests run on PHP 8.4.** The project needs 8.4. Some machines have older versions, which is why tests can fail locally but pass in CI.
- **Fix the four already-failing tests first.** (`ConfiguratorEntryBarTest` and `CutStationJobPickerTest`.) A required check that is red from day one just trains everyone to ignore red. Either fix them or temporarily skip them.

### Custom "house rule" checks

These are small scripts that enforce rules from our `CLAUDE.md`:

- **Parser revision rule:** if `pm-worker/estimate_parser.py` changes, `BidEstimateImporter::CURRENT_PARSER_REVISION` must change too.
- **No JSON data files:** PM data lives in database tables, never `.json` files.
- **No new `Product::adjustQuantity()` calls:** it's deprecated and causes stock numbers to drift.
- **Migration sanity:** fail if two migrations share the same timestamp or are out of order.

---

## Part 2: GitHub settings

Go to **Settings → Branches** (or **Rules → Rulesets**) in the repo and set up `develop` and `main` like this:

- ✅ **Require a pull request** (no pushing directly)
- ✅ **Require the CI checks to pass** (the table above)
- ✅ **Require the branch to be up to date** before merging
- ✅ **Require 1 approval**, and **dismiss approvals if new commits are pushed**
- ✅ **Squash merge only**: one tidy commit per PR
- ✅ **Auto-delete branches after merge**
- 🟡 **Merge queue** (optional): if several PRs are open at once, it re-tests each one *after* combining them

---

## Part 3: CODEOWNERS (who reviews what)

Create `.github/CODEOWNERS`. It makes GitHub automatically ask the right person to review.

Rough split:

- **PM developer owns:** PM pages (`resources/views/pm/`), `app/Services/Pm/`, `pm-worker/`, PM controllers.
- **You (shared-code owner) must review:** `routes/api.php`, `bootstrap/app.php`, `database/migrations/`, the `Product` and `InventoryLocation` models, `config/navigation*.php`, `layouts/app.blade.php`, `.github/`.

Rule of thumb: **PM-only changes can move fast. Anything touching shared files gets a second pair of eyes.**

---

## Part 4: PR template

Create `.github/pull_request_template.md`. GitHub pre-fills it into every new PR:

```markdown
## What does this change?
(one or two sentences)

## Checklist
- [ ] I added/changed a migration (and it's safe to run on production data)
- [ ] Any new API route that changes data has a `permission:` middleware
- [ ] This does / doesn't touch Fabrication queries (FabricationVisibleScope)
- [ ] This does / doesn't affect the field app
- [ ] I updated docs/plans/pm-suite-integration.md if a decision changed

## How I tested it
(what you clicked / ran)
```

---

## Part 5: Habits that prevent merge pain

The robot catches mistakes, but these habits stop the conflicts from happening:

1. **Small, short-lived branches.** Branch from `develop`, merge within a few days. The longer a branch lives, the worse the conflicts.
2. **Pull `develop` into your branch often.** Little conflicts now are much better than a giant one later.
3. **Tell each other before editing shared files.** Especially `routes/api.php`.
4. **Migrations: one person at a time when possible.** If you both need one, use clearly different timestamps and run `migrate:fresh` locally before pushing.
5. **Don't reformat files you're not changing.** It makes the diff huge and the conflicts worse.

### Two structural fixes worth doing

- **Split PM routes into their own file** (e.g. `routes/api_pm.php`). Right now everything lives in one big `routes/api.php` where order matters. Two people editing it = constant conflicts.
- **Move PM page JavaScript out of the Blade files** into separate `.js` files. Two people editing the same giant inline `<script>` block is a conflict waiting to happen.

---

## Part 6: Day-to-day workflow for the new developer

1. Create a branch from `develop`: `git checkout develop && git pull && git checkout -b pm/my-feature`
2. Make changes; commit often.
3. Push and open a PR into `develop`. Fill in the template.
4. Wait for the checks. 
   - **Green:** request review.
   - **Red:** click "Details" next to the failing check, read the error, fix, push again. Checks re-run automatically.
5. Get approval, then **Squash and merge**.
6. Delete the branch (GitHub does this automatically if enabled).

### If a check fails and you don't understand why

Copy the error text from the failed check and ask Claude: "this CI check failed, here's the output, what's wrong?" Most failures are one small fix.

---

## Rollout order (suggested)

Don't turn everything on at once. Suggested order:

1. **Fix the four known-failing tests**, so CI starts green.
2. **Add the CI workflow** with PHP tests, JS smoke tests, pm-worker tests, and the frontend build.
3. **Add Pint** (with a one-time "format everything" commit).
4. **Add branch protection** and make those checks required.
5. **Add CODEOWNERS and the PR template.**
6. **Add the Postgres migration check and house-rule scripts.**
7. **Do the route/JS splitting** when convenient.
