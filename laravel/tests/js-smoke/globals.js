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
  // Every function an inline handler (onclick="foo(...)") or a known shared helper calls must exist as a global.
  const html = fs.readFileSync('pages/' + f, 'utf8');
  const BUILTIN = new Set(['event', 'this', 'confirm', 'prompt', 'alert', 'parseInt', 'parseFloat', 'JSON', 'Number', 'String', 'Math', 'Date', 'Promise', 'setTimeout', 'clearTimeout', 'encodeURIComponent', 'decodeURIComponent', 'if', 'return', 'function', 'typeof']);
  const called = new Set();
  for (const m of html.matchAll(/\bon(?:click|change|input|submit|keyup|blur)="([^"]*)"/g))
    for (const c of m[1].matchAll(/(?<![.\w$])([A-Za-z_$][\w$]*)\s*\(/g)) if (!BUILTIN.has(c[1])) called.add(c[1]);
  for (const h of ['safeShowModal', 'safeHideModal', 'authenticatedFetch', 'authenticatedUpload', 'escapeHtml', 'applyActionPermissions'])
    if (new RegExp('\\b' + h + '\\(').test(html)) called.add(h);
  for (const name of called) if (typeof dom.window[name] !== 'function') errors.push('SyntaxError-like: ' + name + '() is called by the page but is not defined');
  const syntax = errors.filter(m => /already been declared|SyntaxError|Unexpected token|is not valid|is not defined/.test(m));
  console.log((syntax.length ? 'FAIL ' : 'OK   ') + f + (syntax.length ? '  ' + syntax.join(' | ') : ''));
  if (syntax.length) bad++;
  dom.window.close();
}
process.exit(bad ? 1 : 0);
