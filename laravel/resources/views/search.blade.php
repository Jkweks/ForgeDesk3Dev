@extends('layouts.app')

@section('title', 'Search - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none"><div class="container-xl"><h1 class="page-title">Search</h1></div></div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="input-icon mb-3">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" class="form-control form-control-lg" id="q" placeholder="Jobs, purchase orders, vendors, submittals, products, work orders..." autofocus>
      </div>
      <div id="results"></div>
    </div>
  </main>
</div>
<script>
  const esc = (s) => escapeHtml(String(s ?? ''));
  let timer = null, seq = 0;

  async function run() {
    const q = document.getElementById('q').value.trim(), mine = ++seq;
    history.replaceState(null, '', q ? '/search?q=' + encodeURIComponent(q) : '/search');
    if (q.length < 2) { results.innerHTML = '<div class="text-muted">Type at least two characters.</div>'; return; }
    const r = await authenticatedFetch('/search?q=' + encodeURIComponent(q));
    if (mine !== seq) return;   // a newer search is already running
    results.innerHTML = r.groups.length ? r.groups.map(g => `<div class="card mb-3"><div class="card-header"><h3 class="card-title">${esc(g.label)}</h3></div>
      <div class="list-group list-group-flush">${g.items.map(i => `<a class="list-group-item list-group-item-action" href="${esc(i.href)}"><div>${esc(i.title)}</div>${i.sub ? `<div class="text-muted small">${esc(i.sub)}</div>` : ''}</a>`).join('')}</div></div>`).join('')
      : '<div class="text-muted">Nothing found.</div>';
  }

  document.addEventListener('DOMContentLoaded', () => {
    const pre = new URLSearchParams(location.search).get('q'); if (pre) q.value = pre;
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => run().catch(console.error), 250); });
    run().catch(console.error);
  });
</script>
@endsection
