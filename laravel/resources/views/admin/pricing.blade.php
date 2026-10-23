{{-- Admin section: Inventory pricing (pane markup + modals + script) --}}
                    <!-- Inventory Management Tab -->
                    <div class="tab-pane" id="tab-inventory" role="tabpanel">
                      <h3 class="mb-4">Inventory Pricing Management</h3>

                      <!-- Pricing Stats -->
                      <div class="row row-deck row-cards mb-4">
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">Total Products</div>
                              <div class="h1 mb-3" id="statTotalProducts">-</div>
                              <div>Tubelite products</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">With Net Cost</div>
                              <div class="h1 mb-3" id="statWithNetCost">-</div>
                              <div>Products priced</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">Stock Length</div>
                              <div class="h1 mb-3" id="statStockLength">-</div>
                              <div>A, E, M, T parts</div>
                            </div>
                          </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                          <div class="card">
                            <div class="card-body">
                              <div class="subheader">Accessories</div>
                              <div class="h1 mb-3" id="statAccessories">-</div>
                              <div>P, S, CP parts</div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Inventory Status Refresh -->
                      <div class="card mb-4">
                        <div class="card-header">
                          <h4 class="card-title">Inventory Status</h4>
                        </div>
                        <div class="card-body">
                          <p class="text-muted mb-3">
                            Recalculates <code>quantity_on_hand</code> and <code>quantity_committed</code> from inventory locations, and <code>on_order_qty</code> from open purchase orders, for every product, then re-evaluates each product's status (In Stock / Low / Critical / Out of Stock / On Order). Run this if statuses appear out of sync or after editing a PO directly.
                          </p>
                          <div class="d-flex align-items-center gap-3">
                            <button class="btn btn-warning" id="btn-refresh-statuses" onclick="refreshInventoryStatuses()">
                              <i class="ti ti-refresh me-1"></i>Refresh All Statuses
                            </button>
                            <span id="refresh-status-result" class="text-muted small"></span>
                          </div>
                        </div>
                      </div>

                      <!-- EZ Estimate Upload Section -->
                      <div class="row">
                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">EZ Estimate Management</h4>
                            </div>
                            <div class="card-body">
                              <p class="text-muted mb-3">
                                Upload an EZ Estimate Excel file to automatically update product pricing.
                                The system will parse pricing data from worksheets and calculate net costs.
                              </p>

                              <div class="mb-3" id="currentFileInfo" style="display: none;">
                                <div class="alert alert-info">
                                  <div class="d-flex align-items-center">
                                    <div class="me-2">
                                      <i class="ti ti-file-spreadsheet fs-2"></i>
                                    </div>
                                    <div class="flex-fill">
                                      <strong id="currentFileName">-</strong>
                                      <div class="text-muted small">
                                        Uploaded: <span id="currentFileDate">-</span>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label">Upload EZ Estimate File</label>
                                <input type="file" class="form-control" id="ezEstimateFile" accept=".xlsx,.xls">
                                <div class="form-text">Accepted formats: .xlsx, .xls (Max 10MB)</div>
                              </div>

                              <button type="button" class="btn btn-primary" onclick="uploadEzEstimate()">
                                <i class="ti ti-upload me-1"></i>Upload & Process
                              </button>

                              <div id="uploadProgress" style="display: none;" class="mt-3">
                                <div class="progress">
                                  <div class="progress-bar progress-bar-indeterminate"></div>
                                </div>
                                <div class="text-muted text-center mt-2">Processing EZ Estimate...</div>
                              </div>

                              <div id="uploadResult" style="display: none;" class="mt-3"></div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">Pricing Calculation Details</h4>
                            </div>
                            <div class="card-body">
                              <h5>Stock Length Parts (A, E, M, T)</h5>
                              <p class="text-muted">
                                <strong>Formula:</strong> Price per Length × Finish Multiplier × Category Multiplier = Net Cost
                              </p>
                              <ul class="text-muted small">
                                <li><strong>SL Formulas worksheet:</strong> Part Number (Column C), Price per Length (Column G), Pricing Category (Column A)</li>
                                <li><strong>Finish Codes worksheet:</strong> Finish Code (Column F), Finish Multiplier (Column H)</li>
                                <li><strong>Multipliers worksheet:</strong> Pricing Category (B4-B12), Category Multiplier (D4-D12)</li>
                              </ul>

                              <hr>

                              <h5>Accessory Parts (P, S)</h5>
                              <p class="text-muted">
                                <strong>Formula:</strong> Price per Package × Category Multiplier = Net Cost
                              </p>
                              <ul class="text-muted small">
                                <li><strong>P Formulas worksheet:</strong> Part Number (Column C), Pricing Category (Column A), Price per Package (Column H)</li>
                                <li><strong>Multipliers worksheet:</strong> Same as above</li>
                              </ul>

                              <div class="alert alert-warning mt-3">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong>Note:</strong> Only products from manufacturer "Tubelite" will be updated.
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>



@push('scripts')

<script>
      async function refreshInventoryStatuses() {
        const btn = document.getElementById('btn-refresh-statuses');
        const result = document.getElementById('refresh-status-result');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…';
        result.textContent = '';
        try {
          const r = await fetch('/api/v1/products/refresh-statuses', {
            method: 'POST',
            credentials: 'include',
            headers: { 'X-CSRF-TOKEN': adminCsrfToken() },
          });
          const data = await r.json();
          result.className = 'text-success small';
          result.textContent = data.message || `Done — ${data.refreshed} products updated.`;
        } catch (e) {
          result.className = 'text-danger small';
          result.textContent = 'Refresh failed. Check the console.';
          console.error(e);
        } finally {
          btn.disabled = false;
          btn.innerHTML = '<i class="ti ti-refresh me-1"></i>Refresh All Statuses';
        }
      }

      function loadPricingStats() {
        fetch('/api/v1/ez-estimate/stats')
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              document.getElementById('statTotalProducts').textContent = data.stats.total_products;
              document.getElementById('statWithNetCost').textContent = data.stats.with_net_cost;
              document.getElementById('statStockLength').textContent = data.stats.stock_length;
              document.getElementById('statAccessories').textContent = data.stats.accessories;
            }
          })
          .catch(error => {
            console.error('Failed to load pricing stats:', error);
          });
      }

      function loadCurrentEzEstimate() {
        fetch('/api/v1/ez-estimate/current-file')
          .then(response => response.json())
          .then(data => {
            if (data.success && data.file) {
              document.getElementById('currentFileName').textContent = data.file.name;
              document.getElementById('currentFileDate').textContent = data.file.uploaded_at;
              document.getElementById('currentFileInfo').style.display = 'block';
            }
          })
          .catch(error => {
            console.error('Failed to load current EZ Estimate:', error);
          });
      }

      function uploadEzEstimate() {
        const fileInput = document.getElementById('ezEstimateFile');
        const file = fileInput.files[0];

        if (!file) {
          alert('Please select a file to upload');
          return;
        }

        // Show progress
        document.getElementById('uploadProgress').style.display = 'block';
        document.getElementById('uploadResult').style.display = 'none';

        const formData = new FormData();
        formData.append('file', file);

        fetch('/api/v1/ez-estimate/upload', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          },
          body: formData
        })
        .then(async response => {
          // Try to parse as JSON, but if it fails, show the text response
          const text = await response.text();
          try {
            return JSON.parse(text);
          } catch (e) {
            throw new Error('Server returned invalid response: ' + text.substring(0, 500));
          }
        })
        .then(data => {
          document.getElementById('uploadProgress').style.display = 'none';
          document.getElementById('uploadResult').style.display = 'block';

          if (data.success) {
            let resultHtml = `
              <div class="alert alert-success">
                <h4 class="alert-title">Success!</h4>
                <div class="text-muted">
                  EZ Estimate processed successfully.<br>
                  <strong>Stock Length Parts Updated:</strong> ${data.stats.stock_length_updated}<br>
                  <strong>Accessory Parts Updated:</strong> ${data.stats.accessory_updated}
                </div>
            `;

            if (data.stats.errors && data.stats.errors.length > 0) {
              resultHtml += `
                <hr>
                <div class="mt-2">
                  <strong>Errors:</strong>
                  <ul class="mb-0">
                    ${data.stats.errors.map(err => `<li class="small">${err}</li>`).join('')}
                  </ul>
                </div>
              `;
            }

            resultHtml += '</div>';
            document.getElementById('uploadResult').innerHTML = resultHtml;

            // Reload stats and file info
            loadPricingStats();
            loadCurrentEzEstimate();

            // Clear file input
            fileInput.value = '';
          } else {
            document.getElementById('uploadResult').innerHTML = `
              <div class="alert alert-danger">
                <h4 class="alert-title">Error</h4>
                <div class="text-muted">${data.message}</div>
              </div>
            `;
          }
        })
        .catch(error => {
          document.getElementById('uploadProgress').style.display = 'none';
          document.getElementById('uploadResult').style.display = 'block';
          document.getElementById('uploadResult').innerHTML = `
            <div class="alert alert-danger">
              <h4 class="alert-title">Error</h4>
              <div class="text-muted">Failed to upload file: ${error.message}</div>
            </div>
          `;
        });
      }

</script>
@endpush
