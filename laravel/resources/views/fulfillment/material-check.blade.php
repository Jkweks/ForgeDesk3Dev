@extends('layouts.app')

@section('title', 'Material Check - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Fulfillment</div>
              <h1 class="page-title">Material Check</h1>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Upload Card -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Upload Estimate File</h3>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label">Select Excel File (.xlsx or .xlsm)</label>
                    <input type="file" class="form-control" id="estimateFile" accept=".xlsx,.xlsm">
                    <small class="form-hint">Upload an EZ Estimate file to check material availability. Checks Stock Lengths and Accessories sheets automatically.</small>
                  </div>
                  <button class="btn btn-primary" onclick="checkMaterials()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11l3 3l8 -8" /><path d="M20 12v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h9" /></svg>
                    Check Materials
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Results Section -->
          <div id="resultsSection" style="display: none;">
            <!-- Summary Cards -->
            <div class="row row-deck row-cards mb-3">
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Total Items</div>
                    <div class="h1 mb-3 text-primary" id="statTotal">0</div>
                    <div>Items in estimate</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Available</div>
                    <div class="h1 mb-3 text-success" id="statAvailable">0</div>
                    <div>Items in stock</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Partial</div>
                    <div class="h1 mb-3 text-warning" id="statPartial">0</div>
                    <div>Items with partial stock</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Unavailable</div>
                    <div class="h1 mb-3 text-danger" id="statUnavailable">0</div>
                    <div>Items out of stock</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Results Table -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Material Check Results</h3>
                    <div class="col-auto ms-auto d-flex gap-2">
                      <button class="btn btn-success btn-sm" id="commitButton" data-bs-toggle="modal" data-bs-target="#commitModal" onclick="prepareCommitModal()" style="display: none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                        Commit to Job
                      </button>
                      <button class="btn btn-sm" onclick="exportResults()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                        Export Results
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="mb-3">
                      <div class="row g-2">
                        <div class="col-md-4">
                          <input type="text" class="form-control" id="searchInput" placeholder="Search results..." onkeyup="filterResults()">
                        </div>
                        <div class="col-md-3">
                          <select class="form-select" id="statusFilter" onchange="filterResults()">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="partial">Partial</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="not_found">Not Found</option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-vcenter" id="resultsTable">
                        <thead>
                          <tr>
                            <th width="40"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th>Status</th>
                            <th>Part #</th>
                            <th>Finish</th>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>Pack Size</th>
                            <th>Required</th>
                            <th>Available</th>
                            <th>Shortage</th>
                            <th>Location</th>
                          </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                        </tbody>
                      </table>
                    </div>
                    <div class="card-footer">
                      <small class="text-muted">
                        <strong>Note:</strong> Quantities entered in EZ Estimate are in packs. Decimal values represent partial packs (e.g., 0.86 = 86 eaches from a 100-pack).
                      </small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Commit Modal -->
    <div class="modal modal-blur fade" id="commitModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Commit Materials to Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="commitForm">
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label required">Job Number</label>
                  <input type="text" class="form-control" id="jobNumber" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label required">Release Number</label>
                  <input type="number" class="form-control" id="releaseNumber" required min="1">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label required">Job Name</label>
                <input type="text" class="form-control" id="jobName" required>
              </div>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label required">Requested By</label>
                  <input type="text" class="form-control" id="requestedBy" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Needed By</label>
                  <input type="date" class="form-control" id="neededBy">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="notes" rows="3"></textarea>
              </div>
              <div class="alert alert-info">
                <strong id="selectedCount">0</strong> items selected for commitment
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" onclick="commitMaterials()">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
              Commit Materials
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
        let checkResults = [];
        let filteredResults = [];
        let selectedItems = new Set();

        async function checkMaterials() {
            const fileInput = document.getElementById('estimateFile');
            const file = fileInput.files[0];

            if (!file) {
                alert('Please select a file');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('mode', 'ez_estimate');

            try {
                const authToken = localStorage.getItem('authToken');
                const response = await fetch('/api/v1/fulfillment/material-check', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${authToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        // Don't set Content-Type - browser will set it automatically with boundary for FormData
                    },
                    body: formData
                });

                if (response.ok) {
                    const data = await response.json();
                    checkResults = data.results;
                    filteredResults = [...checkResults];
                    displayResults(data.results, data.summary);
                } else {
                    const error = await response.json();
                    alert('Error checking materials: ' + (error.error || error.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error checking materials:', error);
                alert('Error checking materials: ' + error.message);
            }
        }

        function displayResults(results, summary) {
            // Update summary cards
            document.getElementById('statTotal').textContent = summary.total;
            document.getElementById('statAvailable').textContent = summary.available;
            document.getElementById('statPartial').textContent = summary.partial;
            document.getElementById('statUnavailable').textContent = summary.unavailable + summary.not_found;

            // Show results section
            document.getElementById('resultsSection').style.display = 'block';

            // Populate table
            updateResultsTable(results);
        }

        function updateResultsTable(results) {
            const tbody = document.getElementById('resultsTableBody');
            tbody.innerHTML = '';

            results.forEach((item, index) => {
                const row = document.createElement('tr');

                let statusBadge = '';
                let statusClass = '';
                let canCommit = false;

                if (item.status === 'available') {
                    statusBadge = '<span class="badge bg-success">Available</span>';
                    statusClass = '';
                    canCommit = true;
                } else if (item.status === 'partial') {
                    statusBadge = '<span class="badge bg-warning">Partial</span>';
                    statusClass = 'table-warning';
                    canCommit = true;
                } else if (item.status === 'unavailable') {
                    statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
                    statusClass = 'table-danger';
                    canCommit = false;
                } else if (item.status === 'not_found') {
                    statusBadge = '<span class="badge bg-secondary">Not Found</span>';
                    statusClass = 'table-secondary';
                    canCommit = false;
                }

                // Only show checkbox for items found in inventory
                const checkboxHtml = item.product_id && canCommit
                    ? `<input type="checkbox" class="item-checkbox" data-index="${index}" onchange="toggleItemSelection(${index})" ${selectedItems.has(index) ? 'checked' : ''}>`
                    : '';

                // Format pack size display
                const packSize = item.pack_size || 1;
                const hasPackSize = packSize > 1;
                const packSizeDisplay = hasPackSize ? packSize : '-';

                // Format required quantity (packs and eaches)
                const reqPacks = item.required_qty_packs ?? item.required_quantity ?? 0;
                const reqEaches = item.required_qty_eaches ?? item.required_quantity ?? 0;
                const requiredDisplay = hasPackSize
                    ? `<span title="${reqEaches} eaches">${reqPacks} pk</span><br><small class="text-muted">${reqEaches} ea</small>`
                    : reqEaches;

                // Format available quantity (packs and eaches)
                const availPacks = item.available_qty_packs ?? 0;
                const availEaches = item.available_qty_eaches ?? item.available_quantity ?? 0;
                const availableDisplay = item.status === 'not_found'
                    ? '-'
                    : (hasPackSize
                        ? `<span title="${availEaches} eaches">${availPacks} pk</span><br><small class="text-muted">${availEaches} ea</small>`
                        : availEaches);

                // Format shortage (packs and eaches)
                const shortPacks = item.shortage_packs ?? item.shortage ?? 0;
                const shortEaches = item.shortage_eaches ?? item.shortage ?? 0;
                const shortageDisplay = shortEaches > 0
                    ? (hasPackSize
                        ? `<span class="text-danger" title="${shortEaches} eaches">${shortPacks} pk</span><br><small class="text-danger">${shortEaches} ea</small>`
                        : `<span class="text-danger">${shortEaches}</span>`)
                    : '-';

                row.className = statusClass;
                row.innerHTML = `
                    <td>${checkboxHtml}</td>
                    <td>${statusBadge}</td>
                    <td><strong>${item.part_number}</strong></td>
                    <td>${item.finish || '-'}</td>
                    <td><code>${item.sku || '-'}</code></td>
                    <td>${item.description || '-'}</td>
                    <td class="text-center">${packSizeDisplay}</td>
                    <td class="text-end">${requiredDisplay}</td>
                    <td class="text-end">${availableDisplay}</td>
                    <td class="text-end">${shortageDisplay}</td>
                    <td>${item.location || '-'}</td>
                `;

                tbody.appendChild(row);
            });

            updateCommitButton();
        }

        function toggleItemSelection(index) {
            if (selectedItems.has(index)) {
                selectedItems.delete(index);
            } else {
                selectedItems.add(index);
            }
            updateCommitButton();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-checkbox');

            selectedItems.clear();
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
                if (selectAll.checked) {
                    const index = parseInt(checkbox.dataset.index);
                    selectedItems.add(index);
                }
            });

            updateCommitButton();
        }

        function updateCommitButton() {
            const commitButton = document.getElementById('commitButton');
            if (selectedItems.size > 0) {
                commitButton.style.display = 'inline-flex';
            } else {
                commitButton.style.display = 'none';
            }
        }

        function filterResults() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            filteredResults = checkResults.filter(item => {
                const matchesSearch = !searchTerm ||
                    item.part_number.toLowerCase().includes(searchTerm) ||
                    (item.finish && item.finish.toLowerCase().includes(searchTerm)) ||
                    (item.sku && item.sku.toLowerCase().includes(searchTerm)) ||
                    (item.description && item.description.toLowerCase().includes(searchTerm));

                const matchesStatus = !statusFilter || item.status === statusFilter;

                return matchesSearch && matchesStatus;
            });

            updateResultsTable(filteredResults);
        }

        function prepareCommitModal() {
            if (selectedItems.size === 0) {
                alert('Please select items to commit');
                event.preventDefault(); // Prevent modal from opening
                return false;
            }

            // Update selected count
            document.getElementById('selectedCount').textContent = selectedItems.size;
        }

        async function commitMaterials() {
            // Validate form
            const form = document.getElementById('commitForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Prepare items array - commit in EACHES (not packs)
            const items = [];
            selectedItems.forEach(index => {
                const item = filteredResults[index];
                if (item.product_id) {
                    // Use eaches values for commitment
                    const requiredEaches = item.required_qty_eaches ?? item.required_quantity ?? 0;
                    const availableEaches = item.available_qty_eaches ?? item.available_quantity ?? 0;

                    items.push({
                        product_id: item.product_id,
                        part_number: item.part_number,
                        finish: item.finish,
                        sku: item.sku,
                        requested_qty: requiredEaches,
                        committed_qty: Math.min(requiredEaches, availableEaches)
                    });
                }
            });

            if (items.length === 0) {
                alert('No valid items to commit');
                return;
            }

            // Prepare request payload
            const payload = {
                job_number: document.getElementById('jobNumber').value,
                release_number: parseInt(document.getElementById('releaseNumber').value),
                job_name: document.getElementById('jobName').value,
                requested_by: document.getElementById('requestedBy').value,
                needed_by: document.getElementById('neededBy').value || null,
                notes: document.getElementById('notes').value || null,
                items: items
            };

            try {
                const authToken = localStorage.getItem('authToken');
                const response = await fetch('/api/v1/fulfillment/commit-materials', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${authToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(payload)
                });

                if (response.ok) {
                    const data = await response.json();

                    // Close modal using Bootstrap's built-in method
                    const modalElement = document.getElementById('commitModal');
                    const modalBackdrop = document.querySelector('.modal-backdrop');

                    modalElement.classList.remove('show');
                    modalElement.setAttribute('aria-hidden', 'true');
                    modalElement.style.display = 'none';
                    document.body.classList.remove('modal-open');

                    if (modalBackdrop) {
                        modalBackdrop.remove();
                    }

                    // Show success message
                    alert(`✅ Materials committed successfully!\n\nReservation ID: ${data.reservation.id}\nJob: ${data.reservation.job_number} Release ${data.reservation.release_number}\nTotal Committed: ${data.reservation.total_committed} items`);

                    // Reset form and selections
                    form.reset();
                    selectedItems.clear();
                    document.getElementById('selectAll').checked = false;
                    updateCommitButton();

                    // You could refresh the results to show updated availability
                    // checkMaterials();
                } else {
                    const error = await response.json();
                    alert('Error committing materials: ' + (error.message || error.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error committing materials:', error);
                alert('Error committing materials: ' + error.message);
            }
        }

        function exportResults() {
            if (checkResults.length === 0) {
                alert('No results to export');
                return;
            }

            // Create CSV content with pack information
            const headers = ['Status', 'Part Number', 'Finish', 'SKU', 'Description', 'Pack Size', 'Required (packs)', 'Required (eaches)', 'Available (packs)', 'Available (eaches)', 'Shortage (packs)', 'Shortage (eaches)', 'Location'];
            const rows = checkResults.map(item => [
                item.status,
                item.part_number,
                item.finish || '',
                item.sku || '',
                item.description || '',
                item.pack_size || 1,
                item.required_qty_packs ?? item.required_quantity ?? 0,
                item.required_qty_eaches ?? item.required_quantity ?? 0,
                item.available_qty_packs ?? 0,
                item.available_qty_eaches ?? item.available_quantity ?? 0,
                item.shortage_packs ?? item.shortage ?? 0,
                item.shortage_eaches ?? item.shortage ?? 0,
                item.location || ''
            ]);

            let csvContent = headers.join(',') + '\n';
            rows.forEach(row => {
                csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            // Download file
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'material-check-results-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
@endsection
