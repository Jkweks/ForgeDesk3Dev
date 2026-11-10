/*
 * Alphanumeric auto-sort for dropdowns.
 *
 * Add `data-sort` to any <select> or <datalist> whose options are a plain
 * list of entities (people, suppliers, jobs, machines…). Options are re-sorted
 * (natural order: "Job 2" < "Job 10", case-insensitive) whenever they change,
 * so it works for lists filled in later by JS. Leading empty-value options
 * ("All…", "Select…") and options with `data-pin` stay on top; the selected
 * option is preserved. Leave the attribute off for lists with a meaningful
 * order (status, priority, workflow steps, units, hierarchies).
 * Hierarchical lists (indented labels) are skipped automatically.
 */
(function () {
  const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

  function sortList(list) {
    const opts = Array.from(list.children).filter(n => n.tagName === 'OPTION');
    if (opts.length < 3 || list.querySelector('optgroup')) return;
    const isSelect = list.tagName === 'SELECT';
    const label = o => (o.textContent || o.value || '').trim();
    const pinned = o => o.hasAttribute('data-pin');
    let head = 0;
    while (head < opts.length && isSelect && (opts[head].value === '' || pinned(opts[head]))) head++;
    const rest = opts.slice(head);
    if (rest.length < 2) return;
    // hierarchy (indented labels) => keep tree order
    if (rest.some(o => /^[ \s]{2,}|^[—–└├↳]/.test(o.textContent))) return;
    const sorted = rest.slice().sort((a, b) => collator.compare(label(a), label(b)));
    if (sorted.every((o, i) => o === rest[i])) return;
    const selected = isSelect ? Array.from(list.selectedOptions) : [];
    const frag = document.createDocumentFragment();
    sorted.forEach(o => frag.appendChild(o));
    list.appendChild(frag);
    if (isSelect) {
      Array.from(list.options).forEach(o => { o.selected = selected.includes(o); });
      if (!list.multiple && !selected.length && list.options.length) list.selectedIndex = 0;
    }
  }

  const pending = new Set();
  function schedule(list) {
    pending.add(list);
    if (pending.size === 1) queueMicrotask(() => {
      const items = Array.from(pending); pending.clear();
      items.forEach(sortList);
    });
  }

  function owner(node) {
    const el = node.nodeType === 1 ? node : node.parentElement;
    const list = el && el.closest && el.closest('select[data-sort], datalist[data-sort]');
    return list;
  }

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches && root.matches('select[data-sort], datalist[data-sort]')) schedule(root);
    root.querySelectorAll && root.querySelectorAll('select[data-sort], datalist[data-sort]').forEach(schedule);
    const o = owner(root); if (o) schedule(o);
  }

  function start() {
    scan(document.body);
    new MutationObserver(muts => {
      muts.forEach(m => {
        const o = owner(m.target); if (o) schedule(o);
        m.addedNodes.forEach(scan);
      });
    }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
})();
