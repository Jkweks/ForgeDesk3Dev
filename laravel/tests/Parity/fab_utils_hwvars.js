// fab_utils hwlib variable reference: runs fab_utils' OWN hwlibResolveVar (extracted verbatim from workspace.html) per saved link.
// Needs hv_configs/hv_links/hv_vars/hv_lockstop.json (psql dumps, see docs/plans/configurator-parity-findings.md) and fab_utils on :8032.
// Writes hwvars.json; copy to storage/app/fab_utils_parity/ then: php artisan configurator:parity-check --hwvars=fab_utils_parity/hwvars.json
// Runs fab_utils' OWN hwlibResolveVar (extracted verbatim from workspace.html) for every saved hwlib link.
const fs = require('fs');
const src = fs.readFileSync('/mnt/homeNAS/Container/fab_utils/configurator/workspace.html', 'utf8');
function extract(name) {
  const m = new RegExp('(?:async )?function ' + name + '\\(').exec(src); if (!m) throw new Error('missing ' + name);
  let i = src.indexOf('{', m.index), depth = 0, j = i;
  for (; j < src.length; j++) { if (src[j] === '{') depth++; else if (src[j] === '}') { depth--; if (!depth) break; } }
  return src.slice(m.index, j + 1);
}
const names = ['hwlibToNum', 'hwlibEvalExpr', 'hwlibParseBranches', 'hwlibSelectBranch', 'hwlibEvaluateCalculated', 'hwlibResolveVar'];
const resolveVar = new Function(names.map(extract).join('\n') + '\nreturn hwlibResolveVar;')();
const J = f => JSON.parse(fs.readFileSync(f));
const configs = J('hv_configs.json'), links = J('hv_links.json'), vars = J('hv_vars.json'), lock = J('hv_lockstop.json');
const API = 'http://localhost:8032/configurator/hwlib_api.php?table=link_variables&link_id=';
const cfgById = new Map(configs.map(c => [c.id, c]));
const variablesByCode = new Map(vars.map(v => [v.code, v]));
const lockStopZLookup = new Map(lock.map(l => [`${l.sys_name}|${l.series_name}`, l.section_height]));
(async () => {
  const rawByLink = new Map();
  for (const l of links) rawByLink.set(l.id, await (await fetch(API + l.id)).json());
  // combo = a door record + its paired frame record (door.frame_config_id), else the lone record
  const comboOf = new Map();
  for (const c of configs) {
    const isDoor = !!c.inputs.stile;
    const key = isDoor ? `d${c.id}` : `f${c.id}`;
    if (isDoor && c.frame_config_id) comboOf.set(c.id, key), comboOf.set(c.frame_config_id, key);
    else if (!comboOf.has(c.id)) comboOf.set(c.id, key);
  }
  const combos = new Map();
  for (const l of links) { const k = comboOf.get(l.saved_config_id) || `x${l.saved_config_id}`; (combos.get(k) || combos.set(k, []).get(k)).push(l); }
  const out = [];
  for (const [key, ls] of combos) {
    const door = ls.map(l => cfgById.get(l.saved_config_id)).find(c => c.inputs.stile) || [...cfgById.values()].find(c => key === `d${c.id}`);
    const frameCfg = [...cfgById.values()].find(c => !c.inputs.stile && comboOf.get(c.id) === key);
    const angle = parseInt(door?.inputs?.openingAngle) || 90;
    const ctx = {
      variablesByCode, lockStopZLookup,
      rawVarsByLink: rawByLink,
      sameComboCategoriesByLink: new Map(ls.map(l => [l.id, ls.map(x => x.category_name)])),
      sameComboLinkIdsByLink: new Map(ls.map(l => [l.id, ls.map(x => x.id)])),
      angleByLink: new Map(ls.map(l => [l.id, angle])),
      frameSysSeriesByLink: new Map(ls.map(l => [l.id, frameCfg?.inputs?.sysName ? { sysName: frameCfg.inputs.sysName, seriesName: frameCfg.inputs.seriesName } : undefined]).filter(x => x[1])),
    };
    const entry = { key, angle, frame: frameCfg ? { sysName: frameCfg.inputs.sysName, seriesName: frameCfg.inputs.seriesName } : null, links: [] };
    for (const l of ls) {
      const raw = rawByLink.get(l.id) || [];
      const results = {}, overrides = {};
      for (const v of raw) {
        results[v.code] = resolveVar(v.code, l.id, ctx);
        if (v.link_value_text != null) overrides[v.code] = v.link_value_text;
      }
      entry.links.push({ id: l.id, item_name: l.item_name, series: l.series, leaf: l.leaf, quantity: l.quantity, overrides, results });
    }
    out.push(entry);
  }
  fs.writeFileSync('hwvars.json', JSON.stringify(out));
  console.log('combos', out.length, 'links', out.reduce((a, e) => a + e.links.length, 0));
})();
