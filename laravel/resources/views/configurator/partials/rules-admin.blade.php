<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h3 class="mb-1">Rules</h3>
    <p class="text-muted mb-0">
      When a condition holds for an opening (a piece of hardware is selected, it has a threshold, a value is above a limit&hellip;),
      a rule changes what the <a href="/config">Configurator</a> generates: add, remove, swap, split or re-size parts, set a prep value, or block release.
      Rules run after the standard frame, door and hardware BOMs, in the order listed.
    </p>
  </div>
  <button type="button" class="btn btn-primary ms-3 text-nowrap" onclick="crOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>New rule</button>
</div>

<div class="table-responsive mb-4">
  <table class="table table-vcenter card-table">
    <thead>
      <tr>
        <th style="width:5%">Order</th>
        <th style="width:22%">Rule</th>
        <th>When</th>
        <th>Then</th>
        <th style="width:8%">Status</th>
        <th style="width:8%"></th>
      </tr>
    </thead>
    <tbody id="cr-tbody"><tr><td colspan="6" class="text-muted">Loading&hellip;</td></tr></tbody>
  </table>
</div>

<div class="card">
  <div class="card-header"><h4 class="card-title mb-0">Try the rules on an opening</h4></div>
  <div class="card-body">
    <p class="text-muted small">Runs the saved, enabled rules against a real opening without saving anything, and lists what they would add or change.</p>
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Job</label>
        <select class="form-select" id="cr-test-job" onchange="crTestFillOpenings()"></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Opening</label>
        <select class="form-select" id="cr-test-config"></select>
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-outline-primary w-100" onclick="crRunTest()"><i class="ti ti-player-play me-1"></i>Run</button>
      </div>
    </div>
    <div id="cr-test-result" class="mt-3"></div>
  </div>
</div>
