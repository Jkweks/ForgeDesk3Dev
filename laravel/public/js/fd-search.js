// Unified client-side search — mirrors App\Support\Search on the server.
//   fdSearch.match(query, ...fields)  → boolean
//   fdSearch.filter(items, query, item => [fields])  → filtered array
// Rules: case-insensitive; whitespace tokens, all must hit (any order); separator-insensitive
// ("ab-123 blk" finds "AB123BLK"); /regex/ queries; typo-tolerant (edit distance) for 4+ char tokens.
(function (global) {
  const strip = s => s.replace(/[^\p{L}\p{N}]+/gu, '');

  function flatten(fields) {
    const out = [];
    for (const f of fields) {
      if (f == null) continue;
      if (Array.isArray(f)) out.push(...flatten(f));
      else out.push(String(f));
    }
    return out;
  }

  // Edit distance with early exit above `max`.
  function within(a, b, max) {
    if (Math.abs(a.length - b.length) > max) return false;
    let prev = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
      const cur = [i];
      let best = i;
      for (let j = 1; j <= b.length; j++) {
        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        if (cur[j] < best) best = cur[j];
      }
      if (best > max) return false;
      prev = cur;
    }
    return prev[b.length] <= max;
  }

  function fuzzyHit(token, hay) {
    const max = token.length >= 8 ? 2 : 1;
    for (const word of hay.split(/[^\p{L}\p{N}]+/u)) {
      if (!word) continue;
      if (within(token, word, max)) return true;
      // typo in the leading part of a longer word ("alumnim" vs "aluminum")
      if (word.length > token.length && within(token, word.slice(0, token.length), max)) return true;
    }
    return false;
  }

  const cache = new Map(); // query → compiled matcher
  function compile(query) {
    const q = String(query ?? '').trim();
    if (!q) return null;
    if (cache.has(q)) return cache.get(q);
    let m;
    const rx = q.length >= 3 && q[0] === '/' && q.endsWith('/');
    let re = null;
    if (rx) { try { re = new RegExp(q.slice(1, -1), 'i'); } catch (_) { re = null; } }
    if (re) {
      m = texts => texts.some(t => re.test(t));
    } else {
      const tokens = q.toLowerCase().split(/\s+/).filter(Boolean);
      m = texts => {
        const hay = texts.join(' \u0001 ').toLowerCase();
        const hayStrip = strip(hay);
        return tokens.every(t => {
          if (hay.includes(t)) return true;
          const ts = strip(t);
          if (ts && hayStrip.includes(ts)) return true;
          return ts.length >= 4 && fuzzyHit(ts, hay);
        });
      };
    }
    if (cache.size > 200) cache.clear();
    cache.set(q, m);
    return m;
  }

  const fdSearch = {
    match(query, ...fields) {
      const m = compile(query);
      return m ? m(flatten(fields)) : true;
    },
    filter(items, query, pick) {
      const m = compile(query);
      if (!m) return items;
      return items.filter(it => m(flatten([pick(it)])));
    },
  };

  global.fdSearch = fdSearch;
})(window);
