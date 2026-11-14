// Run a PM page's own inline script in jsdom against canned API data; report runtime errors.
const { JSDOM } = require('jsdom');
const fs = require('fs');

const data = {
  '/pm/jobs/1': { id: 1, job_number: '9001', job_name: 'Demo', status: 'won', status_label: 'Won', origin: 'pm', customer_name: 'ACME GC', site_address: '1 Main' },
  '/pm/jobs': [{ id: 1, job_number: '9001', job_name: 'Demo', status: 'won', status_label: 'Won', origin: 'pm' }],
  '/pm/cost-codes': [{ code: 200, category: 'material', description: 'Glass' }, { code: 100, category: 'labor', description: 'Fab' }],
  '/pm/jobs/1/estimates': [{ sheet: 'CONTRACT', contract_amount: 1000, review_count: 1, line_count: 3 }],
  '/pm/jobs/1/estimates/CONTRACT': { parserStale: true, summary: { contractAmount: 1000, material: 500 }, byCostCode: { 200: { description: 'Glass', lineCount: 1, qty: 2, total: 500 } },
    reviewNeeded: [{ section: 'material', row: 4, description: 'x', total: 10, reviewReason: 'no_cost_code_found', costCode: null }], decisions: [{ section: 'material', row: 5, action: 'ignore', description: 'y', total: 5 }],
    warnings: [{ type: 'section_total_mismatch', section: 'labor', computedFromLines: 1, statedInSheet: 2 }], lines: [{ costCode: 200, section: 'material', row: 1, who: 'S', description: 'L', qty: 2, unitPrice: 250, total: 500 }] },
  '/pm/jobs/1/forecast': { rows: [{ costCode: 200, description: 'Glass', estimatedTotal: 500, estimatedQty: 2, orderedTotal: 100, orderedQty: 1, orderedPct: 20, draftTotal: 0, receivedTotal: 0, receivedPct: null, status: 'ordered' }], totals: { estimatedTotal: 500, orderedTotal: 100, draftTotal: 0, receivedTotal: 0 } },
  '/pm/jobs/1/submittals': [{ id: 3, number: '001', name: 'Storefront', spec_section: '08', kind: 'docs', status: 'pending', revision_number: 1, sent_at: '2026-11-01', purchase_orders: [{ id: 9, po_number: 'PO-1' }] }],
  '/pm/jobs/1/submittals/3': { id: 3, status: 'pending', responses: [{ status: 'approved', returned_at: '2026-11-02', file: { original_name: 'm.pdf' }, revision_number: 0, pm_file_id: 4 }], samples: [{ id: 1, description: 'S', manufacturer: 'K', status: 'pending' }],
    purchase_orders: [{ id: 9, po_number: 'PO-1' }], items: [{ id: 1, label: 'Spec' }, { id: 2, label: 'Shop' }], packages: [{ pm_file_id: 7, file: { original_name: 'Submittal_9001_001.pdf' }, item_count: 2, created_at: '2026-11-03' }] },
  '/pm/library/products': [{ id: 1, name: 'Trifab', documents_count: 1, manufacturer: { name: 'Kawneer' } }],
  '/pm/library/products/1': { id: 1, pm_manufacturer_id: 1, name: 'Trifab', is_active: true, documents: [{ id: 1, kind: 'spec', label: 'Spec', pm_file_id: 4, file: { original_name: 's.pdf' } }] },
  '/pm/jobs/1/drawing-sets/1': { id: 1, status: 'done', set_finish: 'CLEAR', elevations: [{ label: 'W1', page: 12, arch_ref: 'A-401', system: 'TUBELITE', finish: 'CLEAR', thus_reqd: 2, opp_reqd: 1, total_reqd: 3, ro_w: '120.5000', ro_h: '96.0000', fs_w: null, fs_h: null, dims: [{ axis: 'w', value: '38.2500' }], glass_tags: [{ tag: 'IG-1' }] }], warnings: [{ message: 'Page 9 unreadable' }] },
  '/pm/jobs/1/estimates/CO%20%231': {}, 
  '/pm/library/lookups': { manufacturers: [{ id: 1, name: 'Kawneer' }], categories: [{ id: 1, name: 'Storefront' }] },
  '/pm/jobs/1/work-order-requests': [{ id: 1, status: 'pending', release_code: 'A', requested_due_date: '2026-12-01', scope: 'x', files: [], checks: [] }],
  '/pm/jobs/1/drawing-sets': [{ id: 1, status: 'done', file: { original_name: 'shop.pdf' }, elevations_count: 1, page_count: 40 }],
  '/pm/vendors': [{ id: 1, name: 'Glass Co' }],
  '/job-purchase-orders?business_job_id=1': { data: [{ id: 9, po_number: 'PO-1' }] },
  '/job-purchase-orders': { data: [{ id: 9, po_number: 'PO-1', voided_at: null, closed_at: null, job: { job_number: '9001' }, supplier: { name: 'Glass Co' }, issue_stage: 'draft', status: 'draft', total_amount: 10, revision_number: 0 }] },
  '/job-purchase-orders/9': { id: 9, po_number: 'PO-1', business_job_id: 1, supplier_id: 1, cost_code: '200', order_date: '2026-11-01', expected_date: null, notes: '', issue_stage: 'material-release', status: 'approved', revision_number: 1, voided_at: null, closed_at: null,
    lines: [{ id: 1, description: 'Group', quantity_ordered: 0, unit_cost: 0, children: [{ id: 2, description: 'A', quantity_ordered: 3, quantity_received: 1, unit_cost: 5, cost_code: '200' }] }, { id: 3, description: 'B', quantity_ordered: 2, quantity_received: 0, unit_cost: 7, children: [] }],
    revisions: [{ revision_number: 0, total_amount: 10, lines: [1], created_at: '2026-11-01' }], receipts: [{ quantity: 1, received_date: '2026-11-02', flagged: true }] },
  '/pm/history': { data: [{ id: 1, action: 'updated', entity_type: 'job', entity_label: '9001', created_at: '2026-11-01T10:00:00', user: { name: 'A' }, fields: [{ field: 'status', old_value: 'bidding', new_value: 'won' }] }], current_page: 1, last_page: 2 },
  '/pm/todos': [{ id: 1, title: 'T', due_date: '2026-11-01', job: { id: 1, job_number: '9001' }, assignee: { name: 'A' }, completed_at: null }],
  '/pm/receiving/outstanding': [{ id: 9, po_number: 'PO-1', supplier: 'G', job: { job_number: '9001' }, expected_date: '2026-11-01', overdue: true, lines: [{ id: 1, description: 'x', quantity_ordered: 3, quantity_received: 1 }] }],
  '/pm/receiving/flagged': [{ po_id: 9, po_number: 'PO-1', supplier: 'G', line: 'x', quantity: 5, received_date: '2026-11-02', notes: '' }],
  '/work-order-requests?status=pending': [{ id: 1, status: 'pending', release_code: 'A', job: { job_number: '9001', job_name: 'D' }, requester: { name: 'A' }, scope: 'x', notes: '', files: [{ id: 1, file: { original_name: 'e.pdf' } }], checks: [{ ok: true, text: 'Job is active' }] }],
};

function run(file, pagePath) {
  const html = fs.readFileSync(`pages/${file}.html`, 'utf8');
  const scripts = [...html.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g)].map(m => m[1]);
  const own = scripts.filter(x => x.includes('DOMContentLoaded') && !x.includes('function authenticatedFetch')).pop();
  if (!own) throw new Error('no page script found in ' + file);
  const body = html.replace(/<script[\s\S]*?<\/script>/g, '');
  const dom = new JSDOM(body, { runScripts: 'outside-only', url: 'http://localhost' + pagePath });
  const w = dom.window, errors = [];
  w.escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  w.safeShowModal = () => {}; w.safeHideModal = () => {}; w.applyActionPermissions = () => {}; w.alert = () => {}; w.confirm = () => true; w.prompt = () => 'x';
  w.authenticatedFetch = async (url) => { const key = url.split('?')[0] in data && !(url in data) ? url.split('?')[0] : url;
    if (!(key in data)) { errors.push('NO STUB for ' + url); return []; } return data[key]; };
  w.authenticatedUpload = async () => ({ ok: true, json: async () => ({}) });
  w.console.error = (...a) => errors.push('console.error: ' + a.map(x => (x && x.message) || String(x)).join(' '));
  w.addEventListener('error', e => errors.push('window error: ' + e.message));
  try { w.eval(own); } catch (e) { errors.push('eval: ' + e.message); }
  return new Promise(res => { w.document.dispatchEvent(new w.Event('DOMContentLoaded')); setTimeout(async () => {
    if (file === 'job') { await w.toggleSubmittal(3); await w.toggleDrawing(1); await w.loadLibDocs(3).catch(() => {}); }
    if (file === 'po') { const before = w.document.querySelectorAll('#linesBody tr').length; w.addGroup(); w.addLine(); const after = w.document.querySelectorAll('#linesBody tr').length; if (after !== before + 3) errors.push('po rows ' + before + ' -> ' + after); const inp = w.document.querySelector('#linesBody tr:last-child input:nth-of-type(1)'); }
    await new Promise(r => setTimeout(r, 200));
    const text = w.document.body.textContent.replace(/\s+/g, ' ');
    res({ file, errors, sample: text.slice(0, 0) , has: (s) => text.includes(s), doc: w.document }); }, 400); });
}

(async () => {
  const checks = [
    ['job', '/pm/jobs/1', ['9001 — Demo', 'Needs your decision', 'Storefront', 'Glass', 'shop.pdf', 'Request work order', 'ACME GC', 'Build package PDF', 'Submittal_9001_001.pdf', 'GC/AE responses', 'Page 9 unreadable', 'IG-1', '38.25']],
    ['po', '/pm/purchase-orders/9', ['PO PO-1', 'Group', 'Receive now', 'Rev 0', 'Flagged']],
    ['pos', '/pm/purchase-orders', ['PO-1']],
    ['library', '/pm/library', ['Trifab']],
    ['history', '/pm/history', ['bidding']],
    ['todos', '/pm/todos', ['9001']],
    ['receiving', '/pm/receiving', ['PO-1', 'Flagged']],
    ['wor', '/fabrication/work-order-requests', ['9001 — D', 'Job is active']],
  ];
  let failed = 0;
  for (const [file, path, expects] of checks) {
    const html0 = fs.readFileSync(`pages/${file}.html`, 'utf8');
    let src = html0;
    if (file === 'po') fs.writeFileSync('pages/po.html', html0.replace(/const PO_ID = [^;]+;/, 'const PO_ID = "9";'));
    const r = await run(file, path);
    if (file === 'po') fs.writeFileSync('pages/po.html', html0);
    const missing = expects.filter(e => !r.has(e));
    const ok = !r.errors.length && !missing.length;
    if (!ok) failed++;
    console.log(ok ? 'OK  ' : 'FAIL', file, r.errors.join(' | '), missing.length ? 'missing: ' + missing.join(', ') : '');
  }
  process.exit(failed ? 1 : 0);
})();
