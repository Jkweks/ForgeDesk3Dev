@extends('layouts.app')

@section('title', 'Category Management - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Organization</div>
              <h1 class="page-title">Category Management</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn btn-primary" onclick="showAddCategoryModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Category
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Stats Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Categories</div>
                  <div class="h1 mb-3" id="statTotalCategories">-</div>
                  <div>All categories</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Root Categories</div>
                  <div class="h1 mb-3" id="statRootCategories">-</div>
                  <div>Top-level categories</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">With Products</div>
                  <div class="h1 mb-3" id="statCategoriesWithProducts">-</div>
                  <div>Categories in use</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Systems</div>
                  <div class="h1 mb-3" id="statSystems">-</div>
                  <div>Unique systems</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Category Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Categories</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="filterSystem" style="width: auto;">
                      <option value="">All Systems</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="btn-group mb-3" role="group">
                    <input type="radio" class="btn-check" name="view-mode" id="view-tree" autocomplete="off" checked>
                    <label class="btn btn-sm" for="view-tree">Tree View</label>

                    <input type="radio" class="btn-check" name="view-mode" id="view-list" autocomplete="off">
                    <label class="btn btn-sm" for="view-list">List View</label>
                  </div>

                  <div class="loading" id="loadingIndicator">
                    <div class="text-muted">Loading categories...</div>
                  </div>

                  <div id="treeView" style="display: none;">
                    <div id="categoryTree"></div>
                  </div>

                  <div id="listView" style="display: none;">
                    <div class="table-responsive">
                      <table class="table table-vcenter card-table table-striped">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Parent</th>
                            <th>System</th>
                            <th>Products</th>
                            <th>Subcategories</th>
                            <th>Status</th>
                            <th class="w-1">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="categoryTableBody">
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal modal-blur fade" id="categoryModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="categoryForm">
            <div class="modal-body">
              <div class="row mb-3">
                <div class="col-md-8">
                  <label class="form-label required">Name</label>
                  <input type="text" class="form-control" id="categoryName" name="name" required>
                  <small class="form-hint">The display name of the category</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Code</label>
                  <input type="text" class="form-control" id="categoryCode" name="code">
                  <small class="form-hint">Unique identifier</small>
                </div>
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Parent Category</label>
                  <select class="form-select" id="categoryParent" name="parent_id">
                    <option value="">None (Root Category)</option>
                  </select>
                  <small class="form-hint">For creating subcategories</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">System Classification</label>
                  <input type="text" class="form-control" id="categorySystem" name="system" list="systemsList">
                  <datalist id="systemsList"></datalist>
                  <small class="form-hint">Optional system grouping</small>
                </div>
              </div>

              <div class="row mb-3">
                <div class="col-md-12">
                  <label class="form-label">Description</label>
                  <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                </div>
              </div>

              <div class="row mb-3">
                <div class="col-md-6">
                  <label class="form-label">Sort Order</label>
                  <input type="number" class="form-control" id="categorySortOrder" name="sort_order" value="0">
                  <small class="form-hint">Lower numbers appear first</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="categoryIsActive" name="is_active" checked>
                    <label class="form-check-label" for="categoryIsActive">Active</label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    let categories = [];
    let systems = [];
    let currentView = 'tree';
    let editingCategoryId = null;

    // Safe modal helpers
    function safeShowModal(modalId) {
      const modalElement = document.getElementById(modalId);
      if (!modalElement) return;

      // Try using Bootstrap if available
      if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
        const modal = new window.bootstrap.Modal(modalElement);
        modal.show();
        return modal;
      }

      // Fallback: manual modal display
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      backdrop.id = 'backdrop-' + modalId;
      document.body.appendChild(backdrop);

      modalElement.style.display = 'block';
      modalElement.classList.add('show');
      modalElement.setAttribute('aria-modal', 'true');
      modalElement.removeAttribute('aria-hidden');
      document.body.classList.add('modal-open');

      // Close on backdrop click
      backdrop.addEventListener('click', () => safeHideModal(modalId));

      // Add close button listeners
      const closeButtons = modalElement.querySelectorAll('[data-bs-dismiss="modal"]');
      closeButtons.forEach(btn => {
        btn.onclick = () => safeHideModal(modalId);
      });

      return null;
    }

    function safeHideModal(modalId) {
      const modalElement = document.getElementById(modalId);
      if (!modalElement) return;

      // Try using Bootstrap if available
      if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getInstance(modalElement);
        if (modal) {
          modal.hide();
          return;
        }
      }

      // Fallback: manual modal hide
      modalElement.style.display = 'none';
      modalElement.classList.remove('show');
      modalElement.setAttribute('aria-hidden', 'true');
      modalElement.removeAttribute('aria-modal');
      document.body.classList.remove('modal-open');

      const backdrop = document.getElementById('backdrop-' + modalId);
      if (backdrop) backdrop.remove();
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
      loadCategories();
      loadSystems();
      setupEventListeners();
    });

    function setupEventListeners() {
      // Search
      document.getElementById('searchInput').addEventListener('input', debounce(loadCategories, 300));

      // System filter
      document.getElementById('filterSystem').addEventListener('change', loadCategories);

      // View mode toggle
      document.getElementById('view-tree').addEventListener('change', () => {
        currentView = 'tree';
        loadCategories(); // Reload with tree endpoint
      });

      document.getElementById('view-list').addEventListener('change', () => {
        currentView = 'list';
        loadCategories(); // Reload with flat list endpoint
      });

      // Form submission
      document.getElementById('categoryForm').addEventListener('submit', handleCategorySubmit);
    }

    async function loadCategories() {
      const search = document.getElementById('searchInput').value;
      const system = document.getElementById('filterSystem').value;

      // For tree view, we need hierarchical data with all descendants
      // For list view, we can use flat data
      let url = currentView === 'tree'
        ? '/category-tree'
        : '/categories?per_page=all&with_parent=true&with_children=true';

      if (search) {
        url += `${url.includes('?') ? '&' : '?'}search=${encodeURIComponent(search)}`;
      }

      if (system) {
        url += `${url.includes('?') ? '&' : '?'}system=${encodeURIComponent(system)}`;
      }

      try {
        const response = await authenticatedFetch(url);
        categories = response;
        renderCategories();
        updateStats();
      } catch (error) {
        console.error('Error loading categories:', error);
        showNotification('Error loading categories', 'danger');
      }
    }

    async function loadSystems() {
      try {
        const response = await authenticatedFetch('/category-systems');
        systems = response;
        populateSystemFilter();
        populateSystemDatalist();
      } catch (error) {
        console.error('Error loading systems:', error);
      }
    }

    function populateSystemFilter() {
      const select = document.getElementById('filterSystem');
      select.innerHTML = '<option value="">All Systems</option>';

      systems.forEach(system => {
        const option = document.createElement('option');
        option.value = system;
        option.textContent = system;
        select.appendChild(option);
      });
    }

    function populateSystemDatalist() {
      const datalist = document.getElementById('systemsList');
      datalist.innerHTML = '';

      systems.forEach(system => {
        const option = document.createElement('option');
        option.value = system;
        datalist.appendChild(option);
      });
    }

    function renderCategories() {
      document.getElementById('loadingIndicator').style.display = 'none';

      if (currentView === 'tree') {
        document.getElementById('treeView').style.display = 'block';
        document.getElementById('listView').style.display = 'none';
        renderTreeView();
      } else {
        document.getElementById('treeView').style.display = 'none';
        document.getElementById('listView').style.display = 'block';
        renderListView();
      }
    }

    function renderTreeView() {
      const container = document.getElementById('categoryTree');
      const rootCategories = categories.filter(cat => !cat.parent_id);

      container.innerHTML = rootCategories.length === 0
        ? '<div class="text-muted">No categories found</div>'
        : rootCategories.map(cat => renderCategoryTree(cat, 0)).join('');
    }

    function renderCategoryTree(category, level) {
      const indent = level * 30;
      const hasChildren = category.children && category.children.length > 0;
      const statusBadge = category.is_active
        ? '<span class="badge text-bg-success">Active</span>'
        : '<span class="badge text-bg-secondary">Inactive</span>';

      let html = `
        <div class="card mb-2" style="margin-left: ${indent}px;">
          <div class="card-body py-2">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex align-items-center">
                  ${hasChildren ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2"><polyline points="6 9 12 15 18 9"></polyline></svg>' : ''}
                  <strong>${escapeHtml(category.name)}</strong>
                  ${category.code ? `<span class="text-muted ms-2">(${escapeHtml(category.code)})</span>` : ''}
                  ${category.system ? `<span class="badge badge-outline ms-2">${escapeHtml(category.system)}</span>` : ''}
                </div>
                <small class="text-muted">
                  ${category.products_count} product${category.products_count !== 1 ? 's' : ''}
                  ${hasChildren ? `, ${category.children.length} subcategor${category.children.length !== 1 ? 'ies' : 'y'}` : ''}
                </small>
              </div>
              <div class="col-auto">
                ${statusBadge}
                <div class="btn-group ms-2">
                  <button class="btn btn-sm btn-ghost-primary" onclick="editCategory(${category.id})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                  </button>
                  <button class="btn btn-sm btn-ghost-danger" onclick="deleteCategory(${category.id}, '${escapeHtml(category.name)}')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      if (hasChildren) {
        html += category.children.map(child => renderCategoryTree(child, level + 1)).join('');
      }

      return html;
    }

    function renderListView() {
      const tbody = document.getElementById('categoryTableBody');

      if (categories.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No categories found</td></tr>';
        return;
      }

      tbody.innerHTML = categories.map(category => {
        const statusBadge = category.is_active
          ? '<span class="badge text-bg-success">Active</span>'
          : '<span class="badge text-bg-secondary">Inactive</span>';

        return `
          <tr>
            <td><strong>${escapeHtml(category.name)}</strong></td>
            <td>${category.code ? escapeHtml(category.code) : '-'}</td>
            <td>${category.parent ? escapeHtml(category.parent.name) : '-'}</td>
            <td>${category.system ? escapeHtml(category.system) : '-'}</td>
            <td>${category.products_count || 0}</td>
            <td>${category.children_count || 0}</td>
            <td>${statusBadge}</td>
            <td>
              <div class="btn-group">
                <button class="btn btn-sm btn-ghost-primary" onclick="editCategory(${category.id})">Edit</button>
                <button class="btn btn-sm btn-ghost-danger" onclick="deleteCategory(${category.id}, '${escapeHtml(category.name)}')">Delete</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function flattenTree(categories) {
      let flat = [];
      categories.forEach(cat => {
        flat.push(cat);
        if (cat.children && cat.children.length > 0) {
          flat = flat.concat(flattenTree(cat.children));
        }
      });
      return flat;
    }

    function updateStats() {
      // If categories is hierarchical (tree view), flatten it first
      const flatCategories = currentView === 'tree' ? flattenTree(categories) : categories;

      const total = flatCategories.length;
      const rootCategories = currentView === 'tree' ? categories.length : flatCategories.filter(cat => !cat.parent_id).length;
      const withProducts = flatCategories.filter(cat => cat.products_count > 0).length;
      const uniqueSystems = new Set(flatCategories.filter(cat => cat.system).map(cat => cat.system)).size;

      document.getElementById('statTotalCategories').textContent = total;
      document.getElementById('statRootCategories').textContent = rootCategories;
      document.getElementById('statCategoriesWithProducts').textContent = withProducts;
      document.getElementById('statSystems').textContent = uniqueSystems;
    }

    function showAddCategoryModal() {
      editingCategoryId = null;
      document.getElementById('categoryModalTitle').textContent = 'Add Category';
      document.getElementById('categoryForm').reset();
      populateParentSelect();

      safeShowModal('categoryModal');
    }

    async function editCategory(id) {
      editingCategoryId = id;
      document.getElementById('categoryModalTitle').textContent = 'Edit Category';

      try {
        const category = await authenticatedFetch(`/categories/${id}`);

        document.getElementById('categoryName').value = category.name || '';
        document.getElementById('categoryCode').value = category.code || '';
        document.getElementById('categoryParent').value = category.parent_id || '';
        document.getElementById('categorySystem').value = category.system || '';
        document.getElementById('categoryDescription').value = category.description || '';
        document.getElementById('categorySortOrder').value = category.sort_order || 0;
        document.getElementById('categoryIsActive').checked = category.is_active;

        populateParentSelect(id);

        safeShowModal('categoryModal');
      } catch (error) {
        console.error('Error loading category:', error);
        showNotification('Error loading category', 'danger');
      }
    }

    function populateParentSelect(excludeId = null) {
      const select = document.getElementById('categoryParent');
      select.innerHTML = '<option value="">None (Root Category)</option>';

      // Flatten categories if in tree view
      const flatCategories = currentView === 'tree' ? flattenTree(categories) : categories;

      // Only show categories that are not the current one or its descendants
      flatCategories
        .filter(cat => !excludeId || (cat.id !== excludeId && !isDescendant(cat, excludeId, flatCategories)))
        .forEach(cat => {
          const option = document.createElement('option');
          option.value = cat.id;
          option.textContent = cat.name + (cat.parent ? ` (${cat.parent.name})` : '');
          select.appendChild(option);
        });
    }

    function isDescendant(category, ancestorId, flatCategories = null) {
      // Use provided flat categories or current categories
      const cats = flatCategories || (currentView === 'tree' ? flattenTree(categories) : categories);

      let current = category;
      while (current.parent_id) {
        if (current.parent_id === ancestorId) return true;
        current = cats.find(c => c.id === current.parent_id);
        if (!current) break;
      }
      return false;
    }

    async function handleCategorySubmit(e) {
      e.preventDefault();

      const formData = {
        name: document.getElementById('categoryName').value,
        code: document.getElementById('categoryCode').value || null,
        parent_id: document.getElementById('categoryParent').value || null,
        system: document.getElementById('categorySystem').value || null,
        description: document.getElementById('categoryDescription').value || null,
        sort_order: parseInt(document.getElementById('categorySortOrder').value) || 0,
        is_active: document.getElementById('categoryIsActive').checked,
      };

      try {
        if (editingCategoryId) {
          await authenticatedFetch(`/categories/${editingCategoryId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData),
          });
          showNotification('Category updated successfully', 'success');
        } else {
          await authenticatedFetch('/categories', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData),
          });
          showNotification('Category created successfully', 'success');
        }

        safeHideModal('categoryModal');
        loadCategories();
        loadSystems(); // Refresh systems list
      } catch (error) {
        console.error('Error saving category:', error);
        showNotification(error.message || 'Error saving category', 'danger');
      }
    }

    async function deleteCategory(id, name) {
      if (!confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
        return;
      }

      try {
        await authenticatedFetch(`/categories/${id}`, {
          method: 'DELETE',
        });
        showNotification('Category deleted successfully', 'success');
        loadCategories();
      } catch (error) {
        console.error('Error deleting category:', error);
        showNotification(error.message || 'Error deleting category', 'danger');
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
    </script>
@endsection
