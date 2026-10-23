<script>
// Shared by the cut station reports (cutStationProductivity, cutStationRecuts, cutStationWaste).
window.CutStationUi = window.CutStationUi || {
  num: (n, d = 0) => Number(n ?? 0).toLocaleString(undefined, { maximumFractionDigits: d }),
  pct: (n) => `${Number(n ?? 0).toFixed(1)}%`,

  // '?start_date=…&end_date=…' from the report's two date inputs (blank = server default, last 30 days)
  query(prefix) {
    const params = new URLSearchParams();
    const start = document.getElementById(`${prefix}Start`).value;
    const end = document.getElementById(`${prefix}End`).value;
    if (start) params.append('start_date', start);
    if (end) params.append('end_date', end);
    return params.toString() ? `?${params}` : '';
  },

  // Fill the date inputs from what the server actually used, so the defaults are visible
  syncDates(prefix, summary) {
    document.getElementById(`${prefix}Start`).value = summary.start_date;
    document.getElementById(`${prefix}End`).value = summary.end_date;
  },

  fill(bodyId, rows, colspan, render) {
    document.getElementById(bodyId).innerHTML = rows.length
      ? rows.map(render).join('')
      : `<tr><td colspan="${colspan}" class="text-center text-muted py-3">Nothing in this date range.</td></tr>`;
  },

  // Colour a rate: red when high enough to chase, otherwise neutral
  rateBadge(rate, warnAt = 5) {
    return `<span class="badge ${rate >= warnAt ? 'bg-red-lt' : 'bg-secondary-lt'}">${this.pct(rate)}</span>`;
  },

  bar(value, max, color = 'primary') {
    const width = max > 0 ? Math.round(value / max * 100) : 0;
    return `<div class="progress progress-sm"><div class="progress-bar bg-${color}" style="width: ${width}%"></div></div>`;
  },

  show(prefix, loaded) {
    document.getElementById(`${prefix}Loading`).style.display = loaded ? 'none' : 'block';
    document.getElementById(`${prefix}Content`).style.display = loaded ? 'block' : 'none';
  },

  async csv(type, prefix) {
    try {
      const sep = this.query(prefix) ? '&' : '';
      const response = await apiCall(`/reports/export?type=${type}${sep}${this.query(prefix).replace('?', '')}`, { headers: { Accept: 'text/csv' } });
      if (!response.ok) throw new Error('Export failed');
      const blob = await response.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `${type}_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(link.href);
    } catch (error) {
      console.error('Cut station export failed:', error);
      showNotification('Could not export the report', 'danger');
    }
  },
};
</script>
