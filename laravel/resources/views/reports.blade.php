@extends('layouts.app')

@section('content')
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-chart-bar me-2"></i>Reports & Analytics
        </h2>
        <div class="text-muted mt-1">Inventory insights and analysis tools</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <button class="btn btn-icon" onclick="refreshAllReports()">
            <i class="ti ti-refresh"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Report Navigation -->
  <div class="page-body">
    <div class="row row-deck row-cards">
      <!-- Report Selector Cards -->
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <input type="search" class="form-control form-control-sm mb-3" id="reportSearch" placeholder="Search reports…" style="max-width: 260px;" oninput="filterReportMenu(this.value)">
            @foreach (config('reports.categories') as $catKey => $catLabel)
              <div class="report-category mb-3" data-category="{{ $catKey }}">
                <div class="text-muted text-uppercase fw-bold small mb-2">{{ $catLabel }}</div>
                <div class="row g-2">
                  @foreach (collect(config('reports.reports'))->where('category', $catKey) as $report)
                    <div class="col-6 col-md-4 col-lg-2 report-menu-item" data-title="{{ strtolower($report['title']) }}">
                      <button class="btn btn-outline-{{ $report['color'] }} w-100" data-permission="{{ $report['permission'] ?? 'reports.view' }}" onclick="showReport('{{ $report['key'] }}')">
                        <i class="ti ti-{{ $report['icon'] }} me-1"></i>
                        {{ $report['title'] }}
                      </button>
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- One partial per report (config/reports.php) --}}
      @foreach (config('reports.reports') as $report)
        @include('reports.partials.'.$report['key'])
      @endforeach

    </div>
  </div>
</div>

<script>
let currentReport = null;

// Show specific report
function showReport(reportType) {
  // Hide all reports
  document.querySelectorAll('[id$="Report"]').forEach(el => el.style.display = 'none');

  // Self-contained report partials register themselves in window.ReportModules
  const mod = window.ReportModules?.[reportType];
  if (mod) {
    document.getElementById(`${reportType}Report`).style.display = 'block';
    currentReport = reportType;
    history.replaceState(null, '', `/reports/${reportType}`);
    mod.load();
    return;
  }
}

// Report menu search: hides non-matching reports, and categories left empty
function filterReportMenu(query) {
  const q = query.trim().toLowerCase();
  document.querySelectorAll('.report-menu-item').forEach(el => {
    el.style.display = !q || el.dataset.title.includes(q) ? '' : 'none';
  });
  document.querySelectorAll('.report-category').forEach(cat => {
    cat.style.display = cat.querySelector('.report-menu-item:not([style*="none"])') ? '' : 'none';
  });
}

// Refresh all visible reports
function refreshAllReports() {
  if (currentReport) {
    showReport(currentReport);
  }
}

// ============================================
// Client-Side Pagination, Sorting & Search
// ============================================
const ITEMS_PER_PAGE = 50;
const reportPaginationState = {};
const reportSortState = {
  lowStock: { sortBy: 'sku', sortDir: 'asc' },
  committed: { sortBy: 'sku', sortDir: 'asc' },
  velocity: { sortBy: 'sku', sortDir: 'asc' },
  reorder: { sortBy: 'sku', sortDir: 'asc' },
  obsolete: { sortBy: 'sku', sortDir: 'asc' },
  inventory: { sortBy: 'part_number', sortDir: 'asc' }
};
const reportSearchState = {
  lowStock: '',
  committed: '',
  velocity: '',
  reorder: '',
  obsolete: '',
  inventory: ''
};

// Debounce timer for search
let reportSearchDebounceTimer = null;

// Sort data by column
function sortReportData(data, sortBy, sortDir) {
  return [...data].sort((a, b) => {
    let aVal = a[sortBy];
    let bVal = b[sortBy];

    // Handle null/undefined values
    if (aVal === null || aVal === undefined) aVal = '';
    if (bVal === null || bVal === undefined) bVal = '';

    // Handle numeric sorting
    if (typeof aVal === 'number' && typeof bVal === 'number') {
      return sortDir === 'asc' ? aVal - bVal : bVal - aVal;
    }

    // Handle string sorting
    const aStr = String(aVal).toLowerCase();
    const bStr = String(bVal).toLowerCase();
    if (sortDir === 'asc') {
      return aStr.localeCompare(bStr);
    } else {
      return bStr.localeCompare(aStr);
    }
  });
}

// Filter data by search term
function filterReportData(data, searchTerm) {
  if (!searchTerm) return data;
  const term = searchTerm.toLowerCase();
  return data.filter(item => {
    const sku = (item.sku || '').toLowerCase();
    const description = (item.description || '').toLowerCase();
    return sku.includes(term) || description.includes(term);
  });
}

// Handle report column sort click
function handleReportSort(reportName, column) {
  const state = reportSortState[reportName];
  if (state.sortBy === column) {
    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
  } else {
    state.sortBy = column;
    state.sortDir = 'asc';
  }
  updateReportSortIcons(reportName);
  renderReportByName(reportName, 1);
}

// Update sort icons for a report
function updateReportSortIcons(reportName) {
  const state = reportSortState[reportName];
  document.querySelectorAll(`.sortable-report[data-report="${reportName}"]`).forEach(th => {
    const icon = th.querySelector('.sort-icon');
    const column = th.dataset.sort;
    if (column === state.sortBy) {
      icon.innerHTML = state.sortDir === 'asc'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 11l-6 -6"/><path d="M6 11l6 -6"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1"><path d="M12 5l0 14"/><path d="M18 13l-6 6"/><path d="M6 13l6 6"/></svg>';
    } else {
      icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1 text-muted"><path d="M8 9l4 -4l4 4"/><path d="M16 15l-4 4l-4 -4"/></svg>';
    }
  });
}

// Handle report search
function handleReportSearch(reportName, searchTerm) {
  clearTimeout(reportSearchDebounceTimer);
  reportSearchDebounceTimer = setTimeout(() => {
    reportSearchState[reportName] = searchTerm;
    renderReportByName(reportName, 1);
  }, 300);
}

// Render report by name
function renderReportByName(reportName, page) {
  switch(reportName) {
    case 'lowStock': renderLowStockTable(page); break;
    case 'committed': renderCommittedTable(page); break;
    case 'velocity': renderVelocityTable(page); break;
    case 'reorder': renderReorderTable(page); break;
    case 'obsolete': renderObsoleteTable(page); break;
    case 'inventory': renderInventoryTable(page); break;
  }
}

// Get filtered and sorted data for a report
function getProcessedReportData(reportName) {
  let data = reportPaginationState[reportName] || [];
  const searchTerm = reportSearchState[reportName];
  const { sortBy, sortDir } = reportSortState[reportName];

  // Filter by search
  data = filterReportData(data, searchTerm);

  // Sort
  data = sortReportData(data, sortBy, sortDir);

  return data;
}

function paginateData(data, page, perPage = ITEMS_PER_PAGE) {
  const startIndex = (page - 1) * perPage;
  const endIndex = startIndex + perPage;
  return {
    data: data.slice(startIndex, endIndex),
    currentPage: page,
    lastPage: Math.ceil(data.length / perPage),
    total: data.length,
    from: data.length > 0 ? startIndex + 1 : 0,
    to: Math.min(endIndex, data.length)
  };
}

function renderReportPagination(containerId, pagination, onPageChange) {
  const container = document.getElementById(containerId);
  if (!container || !pagination || pagination.total === 0) {
    if (container) container.style.display = 'none';
    return;
  }

  const { currentPage, lastPage, total, from, to } = pagination;

  if (lastPage <= 1) {
    container.style.display = 'none';
    return;
  }

  // Build page numbers - show max 7 pages
  const pageNumbers = getReportPageNumbers(currentPage, lastPage, 7);

  let html = `
    <p class="m-0 text-muted">Showing ${from} to ${to} of ${total.toLocaleString()} items</p>
    <ul class="pagination m-0 ms-auto">
      <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}" tabindex="-1" ${currentPage === 1 ? 'aria-disabled="true"' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
            <path d="M15 6l-6 6l6 6"></path>
          </svg>
        </a>
      </li>
  `;

  pageNumbers.forEach(pageNum => {
    if (pageNum === '...') {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    } else {
      html += `
        <li class="page-item ${pageNum === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" data-page="${pageNum}">${pageNum}</a>
        </li>
      `;
    }
  });

  html += `
      <li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}" ${currentPage === lastPage ? 'aria-disabled="true"' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
            <path d="M9 6l6 6l-6 6"></path>
          </svg>
        </a>
      </li>
    </ul>
  `;

  container.innerHTML = html;
  container.style.display = 'flex';

  // Add click handlers
  container.querySelectorAll('.page-link[data-page]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const page = parseInt(link.dataset.page);
      if (page >= 1 && page <= lastPage && page !== currentPage) {
        onPageChange(page);
      }
    });
  });
}

function getReportPageNumbers(current, last, maxVisible) {
  if (last <= maxVisible) {
    return Array.from({length: last}, (_, i) => i + 1);
  }

  const pages = [];
  const half = Math.floor(maxVisible / 2);

  if (current <= half + 1) {
    for (let i = 1; i <= maxVisible - 2; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  } else if (current >= last - half) {
    pages.push(1);
    pages.push('...');
    for (let i = last - maxVisible + 3; i <= last; i++) pages.push(i);
  } else {
    pages.push(1);
    pages.push('...');
    for (let i = current - 1; i <= current + 1; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  }

  return pages;
}

// ============================================
// Helper functions
// ============================================
function getStatusBadge(status) {
  const badges = {
    'in_stock':     '<span class="badge text-bg-success">In Stock</span>',
    'low':          '<span class="badge text-bg-warning">Low Stock</span>',
    'very_low':     '<span class="badge text-bg-orange">Very Low</span>',
    'critical':     '<span class="badge text-bg-danger">Critical</span>',
    'out_of_stock': '<span class="badge text-bg-dark">Out of Stock</span>',
    'on_order':     '<span class="badge text-bg-info">On Order</span>'
  };
  return badges[status] || status;
}

function formatCurrency(value) {
  // Use the global formatPrice function which handles permission-based masking
  if (typeof formatPrice === 'function') {
    return formatPrice(value);
  }
  // Fallback if formatPrice not available
  return '$' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/**
 * Format committed quantity showing packs if applicable
 * Shows "X packs" with eaches in tooltip when pack_size > 1
 */
function formatPackCommitted(item) {
  const committed = item.committed || 0;
  const committedPacks = item.committed_packs || committed;
  const packSize = item.pack_size || 1;
  const hasPackSize = packSize > 1;

  if (committed === 0) {
    return hasPackSize ? '0 packs' : '0';
  }

  if (hasPackSize) {
    const packLabel = committedPacks === 1 ? 'pack' : 'packs';
    return `<span title="${committed} eaches (${packSize}/pack)">${committedPacks} ${packLabel}</span>`;
  }

  return committed.toLocaleString();
}

/**
 * Format on-hand quantity showing packs if applicable
 */
function formatPackOnHand(item) {
  const onHand = item.on_hand || 0;
  const onHandPacks = item.on_hand_packs || onHand;
  const packSize = item.pack_size || 1;
  const hasPackSize = packSize > 1;

  if (hasPackSize) {
    const packLabel = onHandPacks === 1 ? 'pack' : 'packs';
    return `<span title="${onHand} eaches (${packSize}/pack)">${onHandPacks} ${packLabel}</span>`;
  }

  return onHand.toLocaleString();
}

/**
 * Format available quantity showing packs if applicable
 */
function formatPackAvailable(item) {
  const available = item.available || 0;
  const availablePacks = item.available_packs || available;
  const packSize = item.pack_size || 1;
  const hasPackSize = packSize > 1;

  if (hasPackSize) {
    const packLabel = availablePacks === 1 ? 'pack' : 'packs';
    return `<span title="${available} eaches">${availablePacks} ${packLabel}</span>`;
  }

  return available.toLocaleString();
}

// Load first report on page load
document.addEventListener('DOMContentLoaded', () => {
  showReport(@json($initialReport ?? 'lowStock'));

  // Add event listeners for sortable headers
  document.querySelectorAll('.sortable-report').forEach(th => {
    th.addEventListener('click', function() {
      const column = this.dataset.sort;
      const report = this.dataset.report;
      if (column && report) {
        handleReportSort(report, column);
      }
    });
  });

  // Add event listeners for search inputs
  const searchInputs = {
    'lowStockSearch': 'lowStock',
    'committedSearch': 'committed',
    'velocitySearch': 'velocity',
    'reorderSearch': 'reorder',
    'obsoleteSearch': 'obsolete'
  };

  Object.entries(searchInputs).forEach(([inputId, reportName]) => {
    const input = document.getElementById(inputId);
    if (input) {
      input.addEventListener('input', (e) => {
        handleReportSearch(reportName, e.target.value.trim());
      });
    }
  });

  // Storage location live search
  const slSearch = document.getElementById('storageLocationSearch');
  if (slSearch) {
    slSearch.addEventListener('input', (e) => {
      clearTimeout(reportSearchDebounceTimer);
      reportSearchDebounceTimer = setTimeout(() => {
        filterStorageLocationReport(e.target.value.trim());
      }, 300);
    });
  }
});
</script>
@endsection
