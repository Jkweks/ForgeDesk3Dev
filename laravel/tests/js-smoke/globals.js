// Load each full page with ALL its inline scripts as real classic scripts (shared global scope), like a browser,
// and report script-level SyntaxErrors such as "Identifier 'x' has already been declared".
const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
let bad = 0;
for (const f of fs.readdirSync('pages').filter(n => n.endsWith('.html')).sort()) {
  const errors = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', e => errors.push((e.detail && e.detail.message) || e.message));
  const dom = new JSDOM(fs.readFileSync('pages/' + f, 'utf8'), { runScripts: 'dangerously', url: 'http://localhost/pm', virtualConsole: vc, pretendToBeVisual: true });
  const syntax = errors.filter(m => /already been declared|SyntaxError|Unexpected token|is not valid/.test(m));
  console.log((syntax.length ? 'FAIL ' : 'OK   ') + f + (syntax.length ? '  ' + syntax.join(' | ') : ''));
  if (syntax.length) bad++;
  dom.window.close();
}
process.exit(bad ? 1 : 0);
