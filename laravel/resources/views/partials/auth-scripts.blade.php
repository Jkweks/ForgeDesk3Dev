<script>
  const API_BASE = '/api/v1';
  let currentUser = null;

  // Load user data from localStorage
  try {
    const userData = localStorage.getItem('userData');
    if (userData) {
      currentUser = JSON.parse(userData);
    }
  } catch (e) {
    console.error('Error parsing user data:', e);
  }

  // Update user badge in header
  function updateUserBadge() {
    if (currentUser) {
      // Update avatar initial
      const userAvatar = document.getElementById('userAvatar');
      if (userAvatar && currentUser.name) {
        userAvatar.textContent = currentUser.name.charAt(0).toUpperCase();
      }

      // Update user name
      const userName = document.getElementById('userName');
      if (userName) {
        userName.textContent = currentUser.name || 'User';
      }

      // Update user email
      const userEmail = document.getElementById('userEmail');
      if (userEmail) {
        userEmail.textContent = currentUser.email || '';
      }

      // Apply navigation permissions
      applyNavigationPermissions();

      // Apply action permissions (buttons, forms, etc.)
      applyActionPermissions();
    }
  }

  // Update user badge on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateUserBadge);
  } else {
    updateUserBadge();
  }

  // Permission helper
  function hasPermission(permission) {
    if (!currentUser || !currentUser.permissions) {
      return false;
    }
    return currentUser.permissions.includes(permission);
  }

  // Pricing visibility helper
  function canViewPricing() {
    return hasPermission('pricing.view');
  }

  // Apply navigation permissions - hide nav items user doesn't have access to
  function applyNavigationPermissions() {
    if (!currentUser || !currentUser.permissions) {
      // If no user or permissions, hide all navigation (will show login page)
      return;
    }

    // Admins always have full navigation access
    if (currentUser.role === 'admin') {
      return;
    }

    // Check if navigation permissions exist in the system
    // If no nav.* permissions exist at all, assume migration hasn't run yet - show all navigation
    const hasAnyNavPermissions = currentUser.permissions.some(p => p.startsWith('nav.'));

    if (!hasAnyNavPermissions) {
      // Navigation permissions not yet set up - show all navigation for backwards compatibility
      console.log('Navigation permissions not found - showing all navigation items');
      return;
    }

    // Find all navigation items with permission requirements
    const navItems = document.querySelectorAll('[data-nav-permission]');

    navItems.forEach(item => {
      const requiredPermission = item.getAttribute('data-nav-permission');

      // Check if user has the required permission
      if (!hasPermission(requiredPermission)) {
        // Hide the entire nav item
        item.style.display = 'none';
      } else {
        // Ensure it's visible (in case it was previously hidden)
        item.style.display = '';
      }
    });
  }

  // Apply action permissions - hide buttons/actions user doesn't have permission for
  function applyActionPermissions() {
    if (!currentUser || !currentUser.permissions) {
      return;
    }

    // Admins always have full access - skip permission filtering
    if (currentUser.role === 'admin') {
      return;
    }

    // Use a Set for O(1) permission lookups instead of Array.includes()
    const permSet = new Set(currentUser.permissions);

    // Single DOM query for all permission-gated elements
    const allElements = document.querySelectorAll('[data-permission], [data-permission-any], [data-permission-all]');

    allElements.forEach(element => {
      let allowed = false;

      if (element.hasAttribute('data-permission')) {
        allowed = permSet.has(element.getAttribute('data-permission'));
      } else if (element.hasAttribute('data-permission-any')) {
        const perms = element.getAttribute('data-permission-any').split(',').map(p => p.trim());
        allowed = perms.some(p => permSet.has(p));
      } else if (element.hasAttribute('data-permission-all')) {
        const perms = element.getAttribute('data-permission-all').split(',').map(p => p.trim());
        allowed = perms.every(p => permSet.has(p));
      }

      if (!allowed) {
        element.style.display = 'none';
        if (element.tagName === 'BUTTON' || element.tagName === 'INPUT' || element.tagName === 'A') {
          element.disabled = true;
          element.setAttribute('aria-hidden', 'true');
          element.tabIndex = -1;
        }
      } else {
        element.style.display = '';
        if (element.tagName === 'BUTTON' || element.tagName === 'INPUT' || element.tagName === 'A') {
          element.disabled = false;
          element.removeAttribute('aria-hidden');
          element.tabIndex = 0;
        }
      }
    });
  }

  // Helper functions for common permission checks
  function canCreate(resource) {
    return hasPermission(`${resource}.create`);
  }

  function canEdit(resource) {
    return hasPermission(`${resource}.edit`);
  }

  function canDelete(resource) {
    return hasPermission(`${resource}.delete`);
  }

  function canView(resource) {
    return hasPermission(`${resource}.view`);
  }

  function canManage(resource) {
    return hasPermission(`${resource}.manage`);
  }

  function canAdjust(resource) {
    return hasPermission(`${resource}.adjust`);
  }

  function canExport(resource) {
    return hasPermission(`${resource}.export`);
  }

  // Watch for dynamically added content and apply permissions
  function initPermissionWatcher() {
    if (!currentUser || !currentUser.permissions) {
      return;
    }

    let permissionDebounceTimer = null;

    // Create a MutationObserver to watch for DOM changes
    const observer = new MutationObserver((mutations) => {
      let shouldReapply = false;

      for (const mutation of mutations) {
        // Check if nodes were added
        if (mutation.addedNodes.length > 0) {
          for (const node of mutation.addedNodes) {
            // Check if the added node or its children have permission attributes
            if (node.nodeType === 1) { // Element node
              if (node.hasAttribute && (
                  node.hasAttribute('data-permission') ||
                  node.hasAttribute('data-permission-any') ||
                  node.hasAttribute('data-permission-all')
                )) {
                shouldReapply = true;
                break;
              } else if (node.querySelector) {
                if (node.querySelector('[data-permission], [data-permission-any], [data-permission-all]')) {
                  shouldReapply = true;
                  break;
                }
              }
            }
          }
        }
        if (shouldReapply) break;
      }

      // Debounce reapplication to avoid thrashing during bulk DOM updates
      if (shouldReapply) {
        clearTimeout(permissionDebounceTimer);
        permissionDebounceTimer = setTimeout(applyActionPermissions, 50);
      }
    });

    // Start observing the document body for changes
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  // Initialize permission watcher after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPermissionWatcher);
  } else {
    initPermissionWatcher();
  }

  // Format price with masking if no permission
  function formatPrice(value, options = {}) {
    if (!canViewPricing()) {
      const length = options.length || 10;
      return '−'.repeat(length); // Using minus sign (U+2212) for visual consistency
    }

    // Format the price if user has permission
    if (value === null || value === undefined || value === '') {
      return options.placeholder || '—';
    }

    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) {
      return options.placeholder || '—';
    }

    const prefix = options.prefix || '$';
    return prefix + num.toFixed(2);
  }

  // Create a pricing element that's masked for users without permission
  function createPriceElement(value, options = {}) {
    const span = document.createElement('span');

    if (!canViewPricing()) {
      span.className = 'price-masked';
      span.setAttribute('aria-label', 'Price hidden');
      span.textContent = formatPrice(value, options);

      // Store actual value in data attribute (hidden but accessible in inspector)
      if (value !== null && value !== undefined) {
        span.setAttribute('data-actual-value', value);
      }
    } else {
      span.className = 'price-visible';
      span.textContent = formatPrice(value, options);
    }

    return span;
  }

  // Bootstrap Modal Helper - handles initialization safely
  function showModal(modalElement) {
    try {
      if (window.bootstrap && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        // Avoid stacking a second backdrop if the modal is already open
        // (e.g. a save handler refreshing content by re-calling the open function)
        if (!modalElement.classList.contains('show')) {
          modal.show();
        }
        return true;
      }
    } catch (e) {
      console.warn('Bootstrap not available, using fallback:', e);
    }

    // Fallback: manually show modal without Bootstrap
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.id = 'modal-backdrop-' + modalElement.id;
    document.body.appendChild(backdrop);

    modalElement.style.display = 'block';
    modalElement.classList.add('show');
    modalElement.removeAttribute('aria-hidden');
    modalElement.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');

    // Close on backdrop click
    backdrop.addEventListener('click', () => hideModal(modalElement));

    // Add close button listeners
    const closeButtons = modalElement.querySelectorAll('[data-bs-dismiss="modal"]');
    closeButtons.forEach(btn => {
      btn.onclick = () => hideModal(modalElement);
    });

    return false;
  }

  function hideModal(modalElement) {
    try {
      if (window.bootstrap && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getInstance(modalElement);
        if (modal) {
          modal.hide();
          return;
        }
      }
    } catch (e) {
      console.warn('Bootstrap not available for hide, using fallback:', e);
    }

    // Fallback: manually hide modal
    modalElement.style.display = 'none';
    modalElement.classList.remove('show');
    modalElement.setAttribute('aria-hidden', 'true');
    modalElement.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');

    const backdrop = document.getElementById('modal-backdrop-' + modalElement.id);
    if (backdrop) {
      backdrop.remove();
    }
  }

  // Reads the XSRF-TOKEN cookie, which Laravel refreshes on every response —
  // always current, unlike the <meta> tag which is stamped once at page load.
  function getXsrfToken() {
    const cookie = document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
  }

  // API Helper
  async function apiCall(endpoint, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-XSRF-TOKEN': getXsrfToken(),
      ...options.headers
    };

    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      credentials: 'include',
      headers
    });

    return response;
  }

  // Authenticated Fetch - returns JSON directly (convenience wrapper)
  async function authenticatedFetch(endpoint, options = {}) {
    let response = await apiCall(endpoint, options);

    // CSRF token mismatch — refresh the cookie and retry once before giving up
    if (response.status === 419) {
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
      response = await apiCall(endpoint, options);
    }

    if (!response.ok) {
      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        localStorage.removeItem('userData');
        currentUser = null;
        showLogin();
        showNotification('Session expired. Please login again.', 'warning');
        throw new Error('Session expired');
      }

      if (response.status === 419) {
        showNotification('Your session security token expired. Please try again.', 'warning');
        throw new Error('CSRF token mismatch');
      }

      const error = await response.json().catch(() => ({ message: 'Request failed' }));

      // Log full error details to console for debugging
      console.error('API Error Details:', {
        endpoint: endpoint,
        status: response.status,
        error: error.error,
        message: error.message,
        line: error.line,
        file: error.file,
        fullResponse: error
      });

      throw new Error(error.message || `HTTP ${response.status}`);
    }

    // No-content responses (e.g. DELETE returning 204) have no body to parse
    if (response.status === 204 || response.headers.get('content-length') === '0') {
      return null;
    }

    return response.json();
  }

  // Authentication
  function showApp() {
    document.getElementById('loginPage').classList.remove('active');
    document.getElementById('app').classList.add('active');
  }

  function showLogin() {
    document.getElementById('loginPage').classList.add('active');
    document.getElementById('app').classList.remove('active');
  }

  // Login Form
  document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const remember = document.getElementById('loginRemember').checked;

    try {
      // Initialize Sanctum session and get a fresh XSRF token (required before first login POST)
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' });

      const response = await fetch('/api/login', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({ email, password, remember })
      });

      if (response.ok) {
        const data = await response.json();
        currentUser = data.user;
        localStorage.setItem('userData', JSON.stringify(data.user));
        location.reload();
      } else {
        const err = await response.json().catch(() => ({}));
        document.getElementById('loginError').textContent = err.message || 'Invalid credentials';
        document.getElementById('loginError').style.display = 'block';
      }
    } catch (error) {
      console.error('Login error:', error);
      document.getElementById('loginError').textContent = 'Login failed';
      document.getElementById('loginError').style.display = 'block';
    }
  });

  // Logout
  document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
    e.preventDefault();
    try {
      await fetch('/api/logout', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': getXsrfToken()
        }
      });
    } catch (_) { /* best-effort — clear local state regardless */ }
    localStorage.removeItem('userData');
    currentUser = null;
    location.reload();
  });

  // Validate session on page load
  async function validateSession() {
    if (!currentUser) {
      showLogin();
      return;
    }

    // Show app immediately using cached user data — avoids flash of login page
    showApp();

    try {
      const response = await apiCall('/user');
      if (!response.ok) {
        localStorage.removeItem('userData');
        currentUser = null;
        showLogin();
        if (response.status === 401) {
          showNotification('Session expired. Please login again.', 'warning');
        }
        return;
      }
      // Refresh user data (permissions may have changed since last login)
      const freshUser = await response.json();
      currentUser = freshUser;
      localStorage.setItem('userData', JSON.stringify(freshUser));
      updateUserBadge();
    } catch (error) {
      // Network error — stay optimistic, API calls will handle 401s
    }
  }

  window.sessionReady = validateSession();

  // Notification helper
  function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
  }

  // Password Reset Handlers

  // Forgot Password Link
  document.getElementById('forgotPasswordLink')?.addEventListener('click', (e) => {
    e.preventDefault();
    const modal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
    modal.show();
  });

  // Forgot Password Form
  document.getElementById('forgotPasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('forgotPasswordEmail').value;
    const errorDiv = document.getElementById('forgotPasswordError');
    const successDiv = document.getElementById('forgotPasswordSuccess');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    // Reset alerts
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';

    // Disable button during request
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';

    try {
      const response = await fetch('/api/password/forgot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      });

      const data = await response.json();

      if (response.ok) {
        successDiv.textContent = data.message;
        successDiv.style.display = 'block';
        document.getElementById('forgotPasswordForm').reset();

        // Close modal after 3 seconds
        setTimeout(() => {
          const modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
          modal?.hide();
        }, 3000);
      } else {
        errorDiv.textContent = data.message || 'Failed to send reset link';
        errorDiv.style.display = 'block';
      }
    } catch (error) {
      console.error('Forgot password error:', error);
      errorDiv.textContent = 'An error occurred. Please try again.';
      errorDiv.style.display = 'block';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Reset Link';
    }
  });

  // Reset Password Form
  document.getElementById('resetPasswordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = document.getElementById('resetToken').value;
    const email = document.getElementById('resetEmail').value;
    const password = document.getElementById('newPassword').value;
    const passwordConfirmation = document.getElementById('confirmPassword').value;
    const errorDiv = document.getElementById('resetPasswordError');
    const successDiv = document.getElementById('resetPasswordSuccess');
    const submitBtn = e.target.querySelector('button[type="submit"]');

    // Reset alerts
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';

    // Validate passwords match
    if (password !== passwordConfirmation) {
      errorDiv.textContent = 'Passwords do not match';
      errorDiv.style.display = 'block';
      return;
    }

    // Validate password length
    if (password.length < 8) {
      errorDiv.textContent = 'Password must be at least 8 characters long';
      errorDiv.style.display = 'block';
      return;
    }

    // Disable button during request
    submitBtn.disabled = true;
    submitBtn.textContent = 'Resetting...';

    try {
      const response = await fetch('/api/password/reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email,
          token,
          password,
          password_confirmation: passwordConfirmation
        })
      });

      const data = await response.json();

      if (response.ok) {
        successDiv.textContent = data.message;
        successDiv.style.display = 'block';
        document.getElementById('resetPasswordForm').reset();

        // Close modal and show login after 2 seconds
        setTimeout(() => {
          const modal = bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal'));
          modal?.hide();
          showNotification('Password reset successful! You can now login.', 'success');

          // Clear URL parameters
          window.history.replaceState({}, document.title, window.location.pathname);
        }, 2000);
      } else {
        const errorMessage = data.message || data.errors?.email?.[0] || 'Failed to reset password';
        errorDiv.textContent = errorMessage;
        errorDiv.style.display = 'block';
      }
    } catch (error) {
      console.error('Reset password error:', error);
      errorDiv.textContent = 'An error occurred. Please try again.';
      errorDiv.style.display = 'block';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Reset Password';
    }
  });

  // Check for password reset token in URL on page load
  function checkPasswordResetToken() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    const email = urlParams.get('email');

    if (token && email) {
      // Verify token is valid
      fetch('/api/password/verify-token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, token })
      })
      .then(response => response.json())
      .then(data => {
        if (data.valid) {
          // Show reset password modal
          document.getElementById('resetToken').value = token;
          document.getElementById('resetEmail').value = email;
          const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
          modal.show();
        } else {
          showNotification(data.message || 'Invalid or expired reset link', 'danger');
          // Clear URL parameters
          window.history.replaceState({}, document.title, window.location.pathname);
        }
      })
      .catch(error => {
        console.error('Token verification error:', error);
        showNotification('Failed to verify reset link', 'danger');
      });
    }
  }

  // Check for reset token on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkPasswordResetToken);
  } else {
    checkPasswordResetToken();
  }
</script>
