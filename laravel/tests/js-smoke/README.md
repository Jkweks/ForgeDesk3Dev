# PM page JS smoke test

Each Project Management page keeps its own script inline in its Blade view, so PHP tests can't see
runtime JS errors. This harness runs every page's own script in jsdom against canned API data and
checks it renders without errors (and a few interactions: submittal detail, drawing results, PO editor).

```bash
# 1. render the pages (inside the app container; writes /tmp/pm-page-*.html)
docker compose exec -T -e DUMP_PM_PAGES=1 app php artisan test --filter DumpPmPagesTest
# 2. copy them next to the script as pages/<name>.html (job, pos, po, library, history, todos, receiving, wor)
mkdir -p /tmp/smoke/pages && for n in dashboard jobs vendors search inbox job pos po library history todos receiving wor; do
  docker compose exec -T app cat /tmp/pm-page-$n.html > /tmp/smoke/pages/$n.html; done
cp laravel/tests/js-smoke/smoke.js /tmp/smoke/
# 3. run it (needs a recent Node; jsdom is installed on the fly)
docker run --rm -v /tmp/smoke:/s -w /s node:22-slim sh -c "npm i --silent jsdom@24 && node smoke.js"
```

`globals.js` loads each full page with all of its inline scripts as real classic scripts in one shared global scope (like a browser) and fails on script-level SyntaxErrors such as `Identifier 'x' has already been declared`, which `smoke.js` cannot see because it runs only the page's own script. Run it the same way (`node globals.js`) over all dumped pages (add dashboard, jobs, vendors, search, inbox to the copy list).

The canned data in `smoke.js` mirrors the API shapes; update it when an endpoint's shape changes.
Remember `config:clear` before and `config:cache` after running tests in the dev container.
