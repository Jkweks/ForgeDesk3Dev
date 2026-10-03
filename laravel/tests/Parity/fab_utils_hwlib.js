// fab_utils hwlib reference: runs fab_utils' OWN computeBOM (extracted verbatim from workspace.html) over saved links.
// Needs hwlinks.json (psql dump, see docs/plans/configurator-parity-findings.md) and fab_utils on :8032. Writes hwlive.json;
// copy it to storage/app/fab_utils_parity/hwlive.json then: php artisan configurator:parity-check --hwlib=fab_utils_parity/hwlive.json
// Runs fab_utils' OWN computeBOM (extracted verbatim from workspace.html) over saved hwlib links.
const fs = require('fs');
const src = fs.readFileSync('/mnt/homeNAS/Container/fab_utils/configurator/workspace.html', 'utf8');
function extract(name) {
  const re = new RegExp('(?:async )?function ' + name + '\\(');
  const m = re.exec(src); if (!m) throw new Error('missing ' + name);
  let i = src.indexOf('{', m.index), depth = 0, j = i;
  for (; j < src.length; j++) { if (src[j] === '{') depth++; else if (src[j] === '}') { depth--; if (!depth) break; } }
  return src.slice(m.index, j + 1);
}
const names = ['isPairHanding', 'effectiveLinkQty', 'handingLRSuffix', 'hwlibHandedPn', 'computeBOM'];
const API = 'http://localhost:8032/configurator/hwlib_api.php';
const fetchJ = async (url) => (await fetch(url)).json();
const computeBOM = new Function('API', 'fetchJ', names.map(extract).join('\n') + '\nreturn computeBOM;')(API, fetchJ);
const links = JSON.parse(fs.readFileSync('hwlinks.json'));
(async () => {
  const byCfg = {};
  for (const l of links) (byCfg[l.saved_config_id] ??= []).push(l);
  const out = {};
  for (const [id, ls] of Object.entries(byCfg)) {
    const all = ls.map(l => ({ item_id: l.item_id, quantity: l.quantity, leaf: l.leaf, series: l.series, item_name: l.item_name, manufacturer: l.manufacturer,
      model_number: l.model_number, pn: l.pn, handed: l.handed, category_name: l.category_name || '', side: 'door', comboSides: ['door', 'frame'], record: { handing: l.handing } }));
    out[id] = { handing: ls[0].handing, links: ls.map(l => ({ item_name: l.item_name, manufacturer: l.manufacturer, model_number: l.model_number, pn: l.pn, quantity: l.quantity, leaf: l.leaf, series: l.series })), rows: await computeBOM(all) };
  }
  fs.writeFileSync('hwlive.json', JSON.stringify(out));
  console.log('configs', Object.keys(out).length);
})();
