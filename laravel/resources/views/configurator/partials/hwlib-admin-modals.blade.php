<!-- Hardware Library: Category Modal -->
<div class="modal modal-blur fade" id="hwlib-cat-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="hwlib-cat-form">
        <div class="modal-header"><h5 class="modal-title">Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="hwlib-cat-id">
          <div class="row">
            <div class="col-md-8 mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="hwlib-cat-name" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" id="hwlib-cat-sort" value="0"></div>
          </div>
          <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="hwlib-cat-description" rows="2"></textarea></div>
          <label class="form-label">Variables</label>
          <div class="form-hint mb-2">Variables assigned here are the ones shown when editing an item in this category.</div>
          <div id="hwlib-cat-variables" class="border rounded p-2" style="max-height:320px; overflow-y:auto;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hardware Library: Variable Modal -->
<div class="modal modal-blur fade" id="hwlib-var-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="hwlib-var-form">
        <div class="modal-header"><h5 class="modal-title">Variable</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="hwlib-var-id">
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Code</label><input type="text" class="form-control text-uppercase" id="hwlib-var-code" placeholder="e.g. CYL_MORTISE" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Label</label><input type="text" class="form-control" id="hwlib-var-label" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Group</label><input type="text" class="form-control" id="hwlib-var-group" list="hwlib-var-group-list" required>
              <datalist id="hwlib-var-group-list"></datalist>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Type</label>
              <select class="form-select" id="hwlib-var-type" onchange="hwVarToggleOptions()">
                <option value="number">Number</option>
                <option value="text">Text</option>
                <option value="boolean">Boolean</option>
                <option value="select">Select</option>
                <option value="degree_matrix">Degree Matrix</option>
              </select>
            </div>
            <div class="col-md-3 mb-3"><label class="form-label">Unit</label><input type="text" class="form-control" id="hwlib-var-unit"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Side</label>
              <select class="form-select" id="hwlib-var-side">
                <option value="">—</option>
                <option value="door">Door</option>
                <option value="frame">Frame</option>
                <option value="both">Both</option>
              </select>
            </div>
            <div class="col-md-3 mb-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" id="hwlib-var-sort" value="0"></div>
          </div>
          <div class="mb-3" id="hwlib-var-options-wrap" style="display:none">
            <label class="form-label">Options <span class="text-muted">(comma-separated, for Select type)</span></label>
            <input type="text" class="form-control" id="hwlib-var-options" placeholder="e.g. Left, Right, Both">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Default Value</label><input type="text" class="form-control" id="hwlib-var-default"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Overrides Variable</label>
              <select class="form-select" id="hwlib-var-overrides"><option value="">— none —</option></select>
            </div>
          </div>
          <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="hwlib-var-notes" rows="2"></textarea></div>
          <div class="row">
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-var-calculated"><span class="form-check-label">Calculated</span></label></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-var-showreport"><span class="form-check-label">Show in report</span></label></div>
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-var-inspection"><span class="form-check-label">Inspection field</span></label></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hardware Library: Item Modal -->
<div class="modal modal-blur fade" id="hwlib-item-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <form id="hwlib-item-form">
        <div class="modal-header"><h5 class="modal-title">Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="hwlib-item-id">
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Category</label>
              <select class="form-select" id="hwlib-item-category" onchange="hwItemRenderValueInputs()" required></select>
            </div>
            <div class="col-md-4 mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="hwlib-item-name" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">PN</label><input type="text" class="form-control" id="hwlib-item-pn"></div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Manufacturer</label><input type="text" class="form-control" id="hwlib-item-manufacturer"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Model Number</label><input type="text" class="form-control" id="hwlib-item-model"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Notes</label><input type="text" class="form-control" id="hwlib-item-notes"></div>
          </div>
          <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label">Min Width</label><input type="number" step="0.0001" class="form-control" id="hwlib-item-minwidth"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Max Width</label><input type="number" step="0.0001" class="form-control" id="hwlib-item-maxwidth"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Min Height</label><input type="number" step="0.0001" class="form-control" id="hwlib-item-minheight"></div>
            <div class="col-md-3 mb-3"><label class="form-label">Max Height</label><input type="number" step="0.0001" class="form-control" id="hwlib-item-maxheight"></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Default Strike Item</label>
              <select class="form-select" id="hwlib-item-strike"><option value="">— none —</option></select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Default Cover Item</label>
              <select class="form-select" id="hwlib-item-cover"><option value="">— none —</option></select>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-12">
              <label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-item-active" checked><span class="form-check-label">Active</span></label>
              <label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-item-vosstandard"><span class="form-check-label">VOS Standard</span></label>
              <label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-item-fieldinstall"><span class="form-check-label">Field Install</span></label>
              <label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-item-handed"><span class="form-check-label">Handed</span></label>
            </div>
          </div>

          <hr>
          <label class="form-label">Prep Values <span class="text-muted">(fields from the item's category)</span></label>
          <div id="hwlib-item-values" class="row g-2 mb-3"></div>

          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0">Backers</label>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="hwItemAddBackerRow()"><i class="ti ti-plus"></i> Backer</button>
          </div>
          <div id="hwlib-item-backers"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hardware Library: Backer Modal -->
<div class="modal modal-blur fade" id="hwlib-backer-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="hwlib-backer-form">
        <div class="modal-header"><h5 class="modal-title">Backer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="hwlib-backer-id">
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">PN</label><input type="text" class="form-control" id="hwlib-backer-pn" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">Description</label><input type="text" class="form-control" id="hwlib-backer-description"></div>
          </div>
          <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="hwlib-backer-notes" rows="2"></textarea></div>
          <div class="mb-3"><label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-backer-active" checked><span class="form-check-label">Active</span></label></div>

          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0">Fasteners</label>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="hwBackerAddFastenerRow()"><i class="ti ti-plus"></i> Fastener</button>
          </div>
          <div id="hwlib-backer-fasteners"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hardware Library: Fastener Modal -->
<div class="modal modal-blur fade" id="hwlib-fastener-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="hwlib-fastener-form">
        <div class="modal-header"><h5 class="modal-title">Fastener</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="hwlib-fastener-id">
          <div class="mb-3"><label class="form-label">PN</label><input type="text" class="form-control" id="hwlib-fastener-pn" required></div>
          <div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" id="hwlib-fastener-description"></div>
          <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="hwlib-fastener-notes" rows="2"></textarea></div>
          <div class="mb-3"><label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-fastener-active" checked><span class="form-check-label">Active</span></label></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hardware Library: Set Modal -->
<div class="modal modal-blur fade" id="hwlib-set-modal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="hwlib-set-form">
        <div class="modal-header"><h5 class="modal-title">Set</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="hwlib-set-id">
          <div class="row">
            <div class="col-md-5 mb-3"><label class="form-label">Job</label>
              <select class="form-select" id="hwlib-set-job" required><option value="">— select —</option></select>
            </div>
            <div class="col-md-5 mb-3"><label class="form-label">Name</label><input type="text" class="form-control" id="hwlib-set-name" required></div>
            <div class="col-md-2 mb-3 d-flex align-items-end">
              <label class="form-check"><input class="form-check-input" type="checkbox" id="hwlib-set-ispair"><span class="form-check-label">Pair</span></label>
            </div>
          </div>
          <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" id="hwlib-set-notes" rows="2"></textarea></div>

          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0">Items</label>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="hwSetAddItemRow()"><i class="ti ti-plus"></i> Item</button>
          </div>
          <div id="hwlib-set-items"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
