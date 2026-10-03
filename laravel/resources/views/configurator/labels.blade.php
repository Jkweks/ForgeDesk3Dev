@extends('layouts.app')

@section('title', 'Door Labels')

@section('styles')
<style>
  /* 4in x 2in leaf checklist labels, 2 x 5 per letter sheet. Hand is shown as fill vs outline
     (not colour) so it reads on black-and-white sheets and thermal labels. */
  .sheets-wrap { display: flex; flex-direction: column; align-items: center; gap: 20px; padding: 16px 0 40px; overflow-x: auto; }
  .hand-l { background: #000; color: #fff; }
  .hand-r { background: #fff; color: #000; }
  .chk-hand { font-size: .16in; font-weight: 800; letter-spacing: .02em; padding: 1px 8px; border-radius: 3px; white-space: nowrap; border: 1.5px solid #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .chk-inswing { font-size: .62rem; font-weight: 800; letter-spacing: .05em; color: #000; }
  .chk-lbl.empty { border: none; }

  .chk-sheet { width: 8.5in; height: 11in; flex-shrink: 0; background: #fff; box-shadow: 0 1px 6px rgba(0,0,0,.14); padding: .4in .3in;
               display: grid; grid-template-columns: 1fr 1fr; column-gap: .1in; grid-auto-rows: 2.04in; row-gap: 0; }
  .chk-lbl { overflow: hidden; padding: .14in .16in; display: flex; flex-direction: column; justify-content: space-between; border: 1px dashed #ddd; color: #111; }
  .chk-job { font-size: .7rem; color: #555; font-family: monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .chk-idrow { display: flex; align-items: baseline; gap: .12in; margin: .04in 0 .06in; }
  .chk-id { font-size: .4in; font-weight: 900; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .chk-list { display: grid; grid-template-columns: 1fr 1fr; column-gap: .14in; row-gap: .045in; }
  .chk-item { display: flex; align-items: center; gap: .07in; font-size: .7rem; color: #222; white-space: nowrap; }
  .chk-box { display: inline-block; width: .15in; height: .15in; border: 1.3px solid #000; flex-shrink: 0; }
  .chk-signoff { display: flex; align-items: baseline; gap: .1in; font-size: .66rem; color: #555; margin-top: .08in; white-space: nowrap; }
  .chk-line { flex: 1; border-bottom: 1px solid #333; height: .16in; }

  .pick-tree details { margin-left: 1rem; }
  .pick-tree summary { cursor: pointer; padding: 2px 0; }
  .pick-doors { margin-left: 1.5rem; display: flex; flex-direction: column; gap: 2px; }

  @media print {
    body * { visibility: hidden; }
    .sheets-wrap, .sheets-wrap * { visibility: visible; }
    .sheets-wrap { position: absolute; left: 0; top: 0; padding: 0; gap: 0; display: block; overflow: visible; }
    .chk-sheet { box-shadow: none; break-after: page; page-break-after: always; }
    .chk-sheet:last-child { break-after: auto; page-break-after: auto; }
    .chk-lbl { border: none; }
    @page { size: 8.5in 11in; margin: 0; }
  }
</style>
@endsection

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">Configurator</div>
          <h1 class="page-title">Door Labels</h1>
          <p class="text-muted mb-0">4" x 2" door leaf checklist labels (10 per sheet) for the doors you select.</p>
        </div>
        <div class="col-auto ms-auto">
          <button class="btn btn-primary" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>

  <main class="page-body">
    <div class="container-xl">
      <div class="card mb-3 d-print-none">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-lg-5">
              <label class="form-label">Doors <span class="text-muted" id="lb-count">(none selected)</span></label>
              <div class="border rounded p-2 pick-tree" id="lb-tree" style="max-height:260px; overflow:auto">Loading…</div>
              <div class="mt-2 btn-list">
                <button class="btn btn-sm btn-outline-secondary" onclick="lbSelectAll(true)">Select all</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="lbSelectAll(false)">Clear</button>
              </div>
            </div>
            <div class="col-lg-7">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Start at label (reusing a part-used sheet)</label>
                  <input type="number" class="form-control" id="lb-start" min="1" max="10" value="1" onchange="lbRender()">
                </div>
              </div>
              <div class="text-muted small mt-3">One label per leaf of each physical door tag: a pair prints an LH and an RH leaf. Solid badge = left hand, outline = right hand.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="sheets-wrap" id="lb-sheets"><div class="text-muted">Select doors to preview labels.</div></div>
    </div>
  </main>
</div>

<script>
let lbSources = [];
let lbLabels = [];

function lbEsc(s) {
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML;
}

async function lbLoadSources() {
  const data = await authenticatedFetch('/config/labels/sources');
  lbSources = data.jobs || [];
  const tree = document.getElementById('lb-tree');
  if (!lbSources.length) { tree.textContent = 'No door configurations yet.'; return; }
  tree.innerHTML = lbSources.map(j => `
    <details open>
      <summary><strong>${lbEsc(j.name)}</strong></summary>
      ${j.work_orders.map(w => `
        <details open>
          <summary>${lbEsc(w.name)}</summary>
          <div class="pick-doors">
            ${w.configurations.map(c => `
              <label class="form-check mb-0">
                <input class="form-check-input lb-cfg" type="checkbox" value="${c.id}" onchange="lbLoad()">
                <span class="form-check-label">${lbEsc(c.tags)} <span class="text-muted small">${lbEsc(c.handing || '')}${c.status !== 'released' ? ' · ' + lbEsc(c.status) : ''}</span></span>
              </label>`).join('')}
          </div>
        </details>`).join('')}
    </details>`).join('');

  const deepLink = new URLSearchParams(location.search).get('config');
  if (deepLink) {
    const box = document.querySelector(`.lb-cfg[value="${CSS.escape(deepLink)}"]`);
    if (box) { box.checked = true; lbLoad(); }
  }
}

function lbSelectAll(on) {
  document.querySelectorAll('.lb-cfg').forEach(b => b.checked = on);
  lbLoad();
}

async function lbLoad() {
  const ids = [...document.querySelectorAll('.lb-cfg:checked')].map(b => b.value);
  document.getElementById('lb-count').textContent = ids.length ? `(${ids.length} selected)` : '(none selected)';
  const wrap = document.getElementById('lb-sheets');
  if (!ids.length) { lbLabels = []; wrap.innerHTML = '<div class="text-muted">Select doors to preview labels.</div>'; return; }

  const qs = new URLSearchParams();
  ids.forEach(i => qs.append('configuration_ids[]', i));

  const data = await authenticatedFetch(`/config/labels?${qs.toString()}`);
  lbLabels = data.labels || [];
  lbRender();
}

function lbHandBadge(cls, l) {
  return l.hand ? `<span class="${cls} hand-${l.side.toLowerCase()}">${lbEsc(l.hand)}${l.pair && cls === 'chk-hand' ? '-PAIR' : ''}</span>` : '';
}

function lbRender() {
  const wrap = document.getElementById('lb-sheets');
  if (!lbLabels.length) { wrap.innerHTML = '<div class="text-muted">No door leaves found for the selected configurations.</div>'; return; }
  const blank = (cls) => `<div class="${cls} empty"></div>`;

  const PER = 10;
  const startAt = Math.min(PER, Math.max(1, parseInt(document.getElementById('lb-start').value, 10) || 1));
  const cells = [...Array(startAt - 1).fill(null), ...lbLabels];
  let html = '';
  for (let i = 0; i < cells.length; i += PER) {
    const chunk = cells.slice(i, i + PER);
    html += '<div class="chk-sheet">' + chunk.map(l => l == null ? blank('chk-lbl') : `
      <div class="chk-lbl">
        <div class="chk-job">${lbEsc(l.job)}</div>
        <div class="chk-idrow">
          <span class="chk-id">${lbEsc(l.door)}</span>
          ${lbHandBadge('chk-hand', l)}
          ${l.inswing ? '<span class="chk-inswing">INSWING</span>' : ''}
        </div>
        <div class="chk-list">${l.items.map(it => `<div class="chk-item"><span class="chk-box"></span>${lbEsc(it)}</div>`).join('')}</div>
        <div class="chk-signoff">Initial <span class="chk-line"></span></div>
      </div>`).join('') + blank('chk-lbl').repeat(PER - chunk.length) + '</div>';
  }
  wrap.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
  window.sessionReady.then(lbLoadSources);
});
</script>
@endsection
