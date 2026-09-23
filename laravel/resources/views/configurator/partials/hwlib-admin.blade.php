<div class="mb-3">
  <h3 class="mb-1">Hardware Library</h3>
  <p class="text-muted mb-0">Categories, variables, items, backers, and fasteners used to resolve prep values and generate a hardware BOM in the <a href="/config">Configurator</a>.</p>
</div>

<ul class="nav nav-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <a href="#hwlib-tab-categories" class="nav-link active" data-bs-toggle="tab" role="tab">Categories</a>
  </li>
  <li class="nav-item" role="presentation">
    <a href="#hwlib-tab-variables" class="nav-link" data-bs-toggle="tab" role="tab">Variables</a>
  </li>
  <li class="nav-item" role="presentation">
    <a href="#hwlib-tab-functions" class="nav-link" data-bs-toggle="tab" role="tab">Functions</a>
  </li>
  <li class="nav-item" role="presentation">
    <a href="#hwlib-tab-items" class="nav-link" data-bs-toggle="tab" role="tab">Items</a>
  </li>
  <li class="nav-item" role="presentation">
    <a href="#hwlib-tab-backers" class="nav-link" data-bs-toggle="tab" role="tab">Backers &amp; Fasteners</a>
  </li>
  <li class="nav-item" role="presentation">
    <a href="#hwlib-tab-sets" class="nav-link" data-bs-toggle="tab" role="tab">Sets</a>
  </li>
</ul>

<div class="tab-content">

  <!-- Categories -->
  <div class="tab-pane active show" id="hwlib-tab-categories" role="tabpanel">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Categories</h3>
        <div class="card-actions">
          <button class="btn btn-sm btn-primary" onclick="hwCatOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Category</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Name</th><th>Description</th><th>Variables</th><th>Items</th><th class="w-1"></th></tr></thead>
          <tbody id="hwlib-cat-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Variables -->
  <div class="tab-pane" id="hwlib-tab-variables" role="tabpanel">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Variables</h3>
        <div class="card-actions">
          <input type="text" class="form-control form-control-sm me-2" id="hwlib-var-search" placeholder="Search…" style="width:200px" oninput="hwVarRender()">
          <button class="btn btn-sm btn-primary" onclick="hwVarOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Variable</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Code</th><th>Label</th><th>Group</th><th>Type</th><th>Flags</th><th class="w-1"></th></tr></thead>
          <tbody id="hwlib-var-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Functions -->
  <div class="tab-pane" id="hwlib-tab-functions" role="tabpanel">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Functions</h3>
        <div class="card-actions">
          <p class="text-muted mb-0 me-3">Hardware options/modes (e.g. EO, NL, DT, QEL, CD) that items can be tagged as supporting, then selected per hardware link on a configuration. Functions sharing a Group are mutually exclusive when selected.</p>
          <button class="btn btn-sm btn-primary" onclick="hwFuncOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Function</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Code</th><th>Label</th><th>Group</th><th>Active</th><th class="w-1"></th></tr></thead>
          <tbody id="hwlib-func-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Items -->
  <div class="tab-pane" id="hwlib-tab-items" role="tabpanel">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Items</h3>
        <div class="card-actions">
          <select class="form-select form-select-sm me-2" id="hwlib-item-catfilter" style="width:220px" onchange="hwItemRender()">
            <option value="">All Categories</option>
          </select>
          <button class="btn btn-sm btn-primary" onclick="hwItemOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Item</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Name</th><th>Category</th><th>Manufacturer</th><th>PN</th><th>Active</th><th class="w-1"></th></tr></thead>
          <tbody id="hwlib-item-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Backers & Fasteners -->
  <div class="tab-pane" id="hwlib-tab-backers" role="tabpanel">
    <div class="row row-cards">
      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Backers</h3>
            <div class="card-actions">
              <button class="btn btn-sm btn-primary" onclick="hwBackerOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Backer</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>PN</th><th>Description</th><th>Fasteners</th><th>Active</th><th class="w-1"></th></tr></thead>
              <tbody id="hwlib-backer-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Fasteners</h3>
            <div class="card-actions">
              <button class="btn btn-sm btn-primary" onclick="hwFastenerOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Fastener</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead><tr><th>PN</th><th>Description</th><th>Active</th><th class="w-1"></th></tr></thead>
              <tbody id="hwlib-fastener-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sets -->
  <div class="tab-pane" id="hwlib-tab-sets" role="tabpanel">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Sets <span class="text-muted ms-1">(job-scoped — manage &amp; apply them from that job's dashboard)</span></h3>
        <div class="card-actions">
          <button class="btn btn-sm btn-primary" onclick="hwSetOpenModal()" data-permission="configurator.catalog.manage"><i class="ti ti-plus me-1"></i>Add Set</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead><tr><th>Name</th><th>Job</th><th>Items</th><th>Pair</th><th class="w-1"></th></tr></thead>
          <tbody id="hwlib-set-tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

</div>
