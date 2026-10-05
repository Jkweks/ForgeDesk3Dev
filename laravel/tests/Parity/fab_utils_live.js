// Live fab_utils reference runner (needs: npm i jsdom@22; fab_utils on :8032; saved.json = saved_configs dump).
// Loads fab_utils' real calculator pages in jsdom, replays each saved config via its own loadConfig(), and writes live.json
// (same shape as saved_configs.json, outputs = freshly calculated). Nothing is saved back to fab_utils.
// Then: copy live.json to storage/app/fab_utils_parity/live_outputs.json and run
//   php artisan configurator:parity-check --path=fab_utils_parity/live_outputs.json
const { JSDOM } = require('jsdom');
const fs = require('fs');
const BASE = 'http://localhost:8032/configurator/';
const rows = JSON.parse(fs.readFileSync('saved.json'));
const kinds = { frame: { page: 'index.html', rows: rows.filter(r => !r.inputs.stile) }, door: { page: 'door-calculator.html', rows: rows.filter(r => r.inputs.stile) } };
const sleep = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  const out = [];
  for (const [kind, k] of Object.entries(kinds)) {
    const dom = await JSDOM.fromURL(BASE + k.page, { runScripts: 'dangerously', resources: 'usable', pretendToBeVisual: true,
      beforeParse(w) {
        w.fetch = (u, o) => fetch(new URL(u, BASE).href, o);
        w.alert = () => {}; w.confirm = () => true;
        w.open = () => null;
        w.scrollTo = () => {}; w.Element.prototype.scrollTo = () => {}; w.HTMLElement.prototype.scrollIntoView = () => {};
      } });
    const w = dom.window;
    for (let i = 0; i < 100 && !(w.allSystems?.length || w.doorTypes || w.DOOR_TYPES || w.TIE_ROD_DB?.length); i++) await sleep(100);
    await sleep(1500);
    w.savedConfigs = k.rows.map(r => ({ id: r.id, label: r.label, inputs: r.inputs, outputs: r.outputs }));
    for (const r of k.rows) {
      w.lastCalcInputs = null; w.lastCalcOutputs = null;
      try { await w.loadConfig(r.id); } catch (e) { out.push({ id: r.id, label: r.label, status: r.status, inputs: r.inputs, outputs: null, error: e.message }); continue; }
      out.push({ id: r.id, label: r.label, status: r.status, inputs: r.inputs, outputs: w.lastCalcOutputs, error: w.lastCalcOutputs ? null : 'no output' });
    }
    w.close();
  }
  fs.writeFileSync('live.json', JSON.stringify(out));
  console.log('rows', out.length, 'errors', out.filter(o => o.error).length);
  console.log(out.filter(o => o.error).slice(0, 5).map(o => o.id + ':' + o.error).join('\n'));
})();
