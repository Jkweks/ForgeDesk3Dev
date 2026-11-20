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
  '/pm/jobs/1/drawing-sets': [{ id: 1, status: 'done', file: { original_name: 'shop.pdf' }, elevations_count: 2, page_count: 40, progress_done: 40, progress_count: 40, skip_summary: '38 pages were not elevations: plans 3', glazing_tags: [{ tag: 'IG-1T', makeup: '1/4 clear' }] },
    { id: 2, status: 'running', file: { original_name: 'second.pdf' }, progress_done: 12, progress_count: 40, progress_note: 'C2' }],
  '/pm/jobs/1/drawing-elevations': { summary: { total: 2, pending: 2, approved: 0, excluded: 0, needs_attention: 1, high: 1, average_confidence: 72 },
    elevations: [
      { id: 7, label: 'W9', page: 20, arch_ref: 'A-409', system: 'TUBELITE', finish: 'CLEAR', total_reqd: 4, ro_w: '60.0000', ro_h: '84.0000', multi_sheet: false, manual_takeoff: null, review_status: 'approved', set: { id: 1, file: 'shop.pdf' }, sheets: [], marks: [], unresolved_axes: [],
        axis_checks: [{ axis: 'w', state: 'closes', ok: true, member: '2.0000' }, { axis: 'h', state: 'closes', ok: true, member: '2.0000' }], confidence: { score: 95, level: 'high', reasons: ['Frame closes and the readings look plausible'] } },
      { id: 6, label: 'W2', page: 2, arch_ref: 'A-402', system: 'TUBELITE', finish: 'CLEAR', total_reqd: 3, ro_w: null, ro_h: '96.0000', multi_sheet: false, manual_takeoff: null, review_status: 'pending', set: { id: 1, file: 'shop.pdf' },
        sheets: [{ page: 2, width: '1224.00', height: '792.00' }], marks: [], unresolved_axes: ['w'],
        axis_checks: [{ axis: 'w', state: 'flagged', ok: false, member: null }, { axis: 'h', state: 'unverifiable', ok: null }],
        confidence: { score: 41, level: 'low', reasons: ['Width: the frame does not close', 'R.O. width not read'] } },
      { id: 5, label: 'W1', page: 12, arch_ref: 'A-401', system: 'TUBELITE', finish: 'CLEAR', total_reqd: 3, ro_w: '120.5000', ro_h: '96.0000', multi_sheet: true, manual_takeoff: null, review_status: 'pending', set: { id: 1, file: 'shop.pdf' },
        sheets: [{ page: 12, width: '1224.00', height: '792.00', breaks: [{ id: 1, num: 1, x: '1020.00' }] }, { page: 13, width: '1224.00', height: '792.00', breaks: [{ id: 2, num: 1, x: '66.00' }, { id: 3, num: 2, x: '1023.00' }] }], unresolved_axes: [],
        axis_checks: [{ axis: 'w', state: 'closes', ok: true, member: '2.0000', used: 3, dropped: 0 }, { axis: 'h', state: 'corrected', ok: true, member: '2.0000', edited: true }],
        confidence: { score: 91, level: 'high', reasons: ['Frame closes and the readings look plausible'] },
        marks: [{ id: 1, page: 12, kind: 'ro_h', idx: 0, value: '96.0000', x0: '842.2', top: '331.6', x1: '855.3', bottom: '361.8', confirmed_value: null, status: 'suggested', confidence: { score: 92, level: 'high', reasons: ['The frame closes on these readings'] } },
                { id: 2, page: 12, kind: 'dlo_w', idx: 0, value: '38.2500', x0: '146', top: '215', x1: '191', bottom: '228', confirmed_value: '38.2500', status: 'confirmed', confidence: { score: 100, level: 'high', reasons: ['Confirmed by a person'] } },
                { id: 3, page: 12, kind: 'dlo_w', idx: 1, value: '40.0000', x0: '200', top: '215', x1: '242', bottom: '228', confirmed_value: '40.5000', status: 'mismatch', confidence: { score: 100, level: 'high', reasons: ['Corrected by a person'] } },
                { id: 9, page: 12, kind: 'dlo_w', idx: 2, value: '53.5000', x0: '1033', top: '215', x1: '1075', bottom: '228', counted: false, confirmed_value: null, status: 'suggested', confidence: { score: 45, level: 'low', reasons: ['Nothing to check this reading against'] } },
                { id: 4, page: 13, kind: 'dlo_w', idx: 0, value: '33.7500', x0: '10', top: '20', x1: '50', bottom: '33', confirmed_value: null, status: 'suggested', confidence: { score: 45, level: 'low', reasons: ['Nothing to check this reading against'] } }] }] },
  '/pm/jobs/1/estimates/CO%20%231': {}, 
  '/pm/library/lookups': { manufacturers: [{ id: 1, name: 'Kawneer' }], categories: [{ id: 1, name: 'Storefront' }] },
  '/pm/jobs/1/work-order-requests': [{ id: 1, status: 'pending', release_code: 'A', requested_due_date: '2026-12-01', scope: 'x', files: [], checks: [] }],
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
  const dom = new JSDOM(body, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'http://localhost' + pagePath });
  const w = dom.window, errors = [];
  w.escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  w.safeShowModal = () => {}; w.safeHideModal = () => {}; w.applyActionPermissions = () => {}; w.alert = () => {}; w.confirm = () => true; w.prompt = () => 'x';
  w.authenticatedFetch = async (url) => { const key = url.split('?')[0] in data && !(url in data) ? url.split('?')[0] : url;
    if (!(key in data)) { errors.push('NO STUB for ' + url); return []; } return data[key]; };
  w.HTMLCanvasElement.prototype.getContext = () => new Proxy({}, { get: (t, k) => (k === 'measureText' ? () => ({ width: 24 }) : (typeof k === 'string' ? () => {} : undefined)), set: () => true });
  w.authenticatedUpload = async () => ({ ok: true, json: async () => ({}) });
  w.console.error = (...a) => errors.push('console.error: ' + a.map(x => (x && x.message) || String(x)).join(' '));
  w.addEventListener('error', e => errors.push('window error: ' + e.message));
  try { w.eval(own); } catch (e) { errors.push('eval: ' + e.message); }
  return new Promise(res => { w.document.dispatchEvent(new w.Event('DOMContentLoaded')); setTimeout(async () => {
    if (file === 'jobs') {   // the whole row opens the job; the stage buttons must not
      const tr = w.document.querySelector('#pmJobsBody tr[data-href]');
      if (!tr || tr.dataset.href !== '/pm/jobs/1') errors.push('jobs list rows are not clickable: ' + (tr && tr.dataset.href));
      if (!/Won/.test(w.document.getElementById('cntWon').parentElement.textContent) || w.document.getElementById('cntWon').textContent !== '1') errors.push('stage cards should count the jobs: ' + w.document.getElementById('cntWon').textContent);
      const btn = tr && tr.querySelector('button'); let navigated = false;
      if (btn) { const ev = new w.MouseEvent('click', { bubbles: true }); Object.defineProperty(ev, 'target', { value: btn }); w.rowClick(ev); }
      if (w.document.querySelectorAll('#pmJobsBody tr.pm-row').length !== 1) errors.push('expected one clickable row');
    }
    if (file === 'job') { await w.toggleSubmittal(3); await w.openSheetViewer(6); await new Promise(r => setTimeout(r, 200)); { const t = w.document.getElementById('szElevInfo').textContent; for (const need of ['Frame size ≠ openings', 'Keep, mark unverified', 'Accept with a reason', 'R.O. width not read']) if (!t.includes(need)) errors.push('flagged elevation panel is missing: ' + need); } await w.openSheetViewer(5); await new Promise(r => setTimeout(r, 250)); w.szFocus(2, false);
      { const heads = w.document.querySelectorAll('#drawList .dw-set-head'); if (!heads.length) errors.push('the drawing files should be headers in the list');
        w.setAllSets(false); const folded = w.document.querySelectorAll('#drawList .dw-set.collapsed').length, all = w.document.querySelectorAll('#drawList .dw-set').length;
        if (all && folded > all) errors.push('collapse all broke the list'); w.setAllSets(true);
        if (w.document.querySelectorAll('#drawList .dw-set.collapsed').length) errors.push('expand all should open every file');
        if (!w.document.querySelector('#drawList .dw-set .dw-set-body .dw-row')) errors.push('elevations should sit inside their file'); }
      { const sh = w.szBreaks({ breaks: [{ num: 2, x: '1023' }, { num: 1, x: '66' }] }); if (sh[0].x !== 66 || sh[1].num !== 2) errors.push('break lines should sort left to right');
        const r = w.szOwnedRange({ width: '1224' }, [{ x: 1020 }]), r2 = w.szOwnedRange({ width: '1224' }, [{ x: 66 }]), r3 = w.szOwnedRange({ width: '1224' }, [{ x: 66 }, { x: 1023 }]);
        if (!(r[0] == null && r[1] === 1020 && r2[0] === 66 && r2[1] == null && r3[0] === 66 && r3[1] === 1023)) errors.push('owned range wrong: ' + [r, r2, r3].map(x => x.join('..')).join(' | '));
        const info = w.document.getElementById('szElevInfo').textContent; if (!/Run across 2 sheets/.test(info)) errors.push('multi-sheet run summary missing: ' + info.slice(0, 120));
        { const cap = w.szCapText(); if (!/left of break line 1/.test(cap)) errors.push('sheet caption should say which part of the run it owns: ' + cap); }
        if (/53 1\/2/.test(w.document.getElementById('szList').textContent)) errors.push('a reading beyond the break lines was listed as this sheet\'s own');
        if (!/beyond its break lines/.test(w.document.getElementById('szList').textContent)) errors.push('the context note is missing from the list'); } w.szFrameElevation(false); w.szFrameSheet(false); w.szZoomBy(1.4); w.szStep(1, true); w.szSetHover(3);
      { const mk = (id, kind, x0, top, x1, bottom) => ({ id, kind, x0: String(x0), top: String(top), x1: String(x1), bottom: String(bottom) });   // frame centred at x≈520: width text above, height text on the right only
        const sh = { width: '1224.00', height: '792.00', title_x0: '900', title_top: '700', title_x1: '1200', title_bottom: '780' };
        const ms = [mk(1, 'ro_w', 500, 100, 540, 110), mk(2, 'ro_h', 842, 331, 855, 362), mk(3, 'dlo_w', 300, 120, 330, 130)], c = w.szCropBox(sh, ms);
        if (!(c[0] >= 0 && c[1] >= 0 && c[2] <= 1224 && c[3] <= 792)) errors.push('sheet crop is outside the sheet: ' + c);
        for (const m of ms) if (!(+m.x0 >= c[0] && +m.x1 <= c[2] && +m.top >= c[1] && +m.bottom <= c[3])) errors.push('sheet crop cuts off value ' + m.id + ': ' + c);
        if (!(c[0] <= 198 && c[2] >= 1200)) errors.push('sheet crop misses the frame\'s far side or the title block: ' + c);   // mirror of 842 about 520 is 198
        { const m = (id, kind, idx, v, x0, top, x1, bottom) => ({ id, kind, idx, value: String(v), x0: String(x0), top: String(top), x1: String(x1), bottom: String(bottom) });   // 46/E22 of the Howell set: only widths were read
          const real = [m(1, 'dlo_w', 0, 45.625, 566.4, 166.49, 608.54, 179.56), m(2, 'dlo_w', 1, 68, 659.76, 166.49, 691.61, 179.56), m(3, 'dlo_w', 2, 68, 764.76, 166.49, 796.61, 179.56),
            m(4, 'dlo_w', 3, 68, 869.76, 166.49, 901.61, 179.56), m(5, 'dlo_w', 4, 45.625, 952.86, 166.49, 995, 179.56), m(6, 'fs_w', 0, 307.25, 761.28, 143.99, 800.34, 157.06), m(7, 'ro_w', 0, 308, 765.78, 121.49, 795.91, 134.56)];
          const fr = w.szFrameExtentX(real), c2 = w.szCropBox({ width: '1224', height: '792', title_x0: '594.84', title_top: '472.21', title_x1: '893.74', title_bottom: '521.48' }, real);
          if (!fr || Math.abs(fr[0] - 551) > 8 || Math.abs(fr[1] - 1011) > 8) errors.push('46/E22 frame should span about 551 to 1011 pt: ' + fr);       // measured off the drawing
          if (!(c2[0] <= 430 && c2[2] >= 1054)) errors.push('46/E22 crop must include the height chain left of the frame (x 430) and the notes right of it (x 1054): ' + c2); }
        const whole = w.szCropBox({ width: '1224', height: '792' }, []); if (whole.join() !== '0,0,1224,792') errors.push('no values: the crop should be the whole sheet'); }
      w.showWoForm();
      { // the drawing is offered, its approved elevation can be ticked, and the quantity defaults to what is still unrequested
        const opts = [...w.document.querySelectorAll('#woSet option')].map(o => o.textContent).join('|');
        if (!opts.includes('shop.pdf')) errors.push('the work order form should offer the drawing: ' + opts);
        const t = w.document.getElementById('woElevList').textContent;
        if (!t.includes('W9') || !t.includes('95%') && !t.includes('4')) errors.push('work order form missing the approved elevation: ' + t.slice(0, 120));
        const cb = w.document.querySelector('#woElevList input[type=checkbox]:not([disabled])');
        if (!cb) errors.push('an approved elevation should be selectable'); else { cb.checked = true; w.woToggle(+cb.value);
          const sel = w.woSelection(); if (sel.length !== 1 || sel[0].quantity !== 4 || sel[0].drawing_elevation_id !== 7) errors.push('selection should be elevation 7 x 4: ' + JSON.stringify(sel)); }
        if (w.document.querySelector('#woElevList input[disabled]') === null && w.document.querySelectorAll('#woElevList tr').length > 1) errors.push('unapproved elevations must not be selectable'); } }
    if (file === 'po') { const before = w.document.querySelectorAll('#linesBody tr').length; w.addGroup(); w.addLine(); const after = w.document.querySelectorAll('#linesBody tr').length; if (after !== before + 3) errors.push('po rows ' + before + ' -> ' + after); const inp = w.document.querySelector('#linesBody tr:last-child input:nth-of-type(1)'); }
    await new Promise(r => setTimeout(r, 200));
    const text = w.document.body.textContent.replace(/\s+/g, ' ');
    res({ file, errors, sample: text.slice(0, 0) , has: (s) => text.includes(s), doc: w.document }); }, 400); });
}

(async () => {
  const checks = [
    ['job', '/pm/jobs/1', ['9001 — Demo', 'Needs your decision', 'Storefront', 'Glass', 'second.pdf', 'page 12 of 40', 'Request work order', 'ACME GC', 'Build package PDF', 'Submittal_9001_001.pdf', 'GC/AE responses',
      'Drop the whole drawing package', 'Glazing schedule', 'IG-1T', 'plans 3', 'Elevations and quantity to release from this drawing', 'doesn’t close', 'corrected · closes', '91% high', '41% low', 'Values on this sheet', 'Next unchecked', 'Need attention', 'Select an elevation to check its sizes', 'Approve 1 high-confidence', '2 elevations'.replace('2 elevations', 'elevations'), 'Confidence', 'R.O. height', 'D.L.O. width #2', '40 1/2"', 'Confirm all', 'uniform member 2"']],
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
