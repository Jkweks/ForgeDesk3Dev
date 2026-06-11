<script>
  const API_BASE = '/api/v1';
  let authToken = localStorage.getItem('authToken');
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
        const modal = new window.bootstrap.Modal(modalElement);
        modal.show();
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

  // API Helper
  async function apiCall(endpoint, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      ...options.headers
    };

    if (authToken) {
      headers['Authorization'] = `Bearer ${authToken}`;
    }

    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers
    });

    return response;
  }

  // Authenticated Fetch - returns JSON directly (convenience wrapper)
  async function authenticatedFetch(endpoint, options = {}) {
    const response = await apiCall(endpoint, options);

    if (!response.ok) {
      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        localStorage.removeItem('tokenExpiresAt');
        localStorage.removeItem('tokenExpiresIn');
        localStorage.removeItem('rememberMe');
        authToken = null;
        currentUser = null;
        if (tokenRefreshTimer) {
          clearInterval(tokenRefreshTimer);
        }
        showLogin();
        showNotification('Session expired. Please login again.', 'warning');
        throw new Error('Session expired');
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
      const response = await fetch('/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, remember })
      });

      if (response.ok) {
        const data = await response.json();
        authToken = data.token;
        currentUser = data.user;
        localStorage.setItem('authToken', authToken);
        localStorage.setItem('userData', JSON.stringify(data.user));
        localStorage.setItem('tokenExpiresAt', data.expires_at);
        localStorage.setItem('tokenExpiresIn', data.expires_in);
        localStorage.setItem('rememberMe', data.remember);
        updateUserBadge(); // Update badge before reload
        startTokenRefreshTimer(); // Start automatic refresh
        location.reload(); // Reload to initialize the app
      } else {
        document.getElementById('loginError').textContent = 'Invalid credentials';
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
        headers: {
          'Authorization': `Bearer ${authToken}`,
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
    } catch (_) { /* best-effort — clear local state regardless */ }
    if (tokenRefreshTimer) clearInterval(tokenRefreshTimer);
    localStorage.removeItem('authToken');
    localStorage.removeItem('userData');
    localStorage.removeItem('tokenExpiresAt');
    localStorage.removeItem('tokenExpiresIn');
    localStorage.removeItem('rememberMe');
    authToken = null;
    currentUser = null;
    location.reload();
  });

  // Token Refresh Mechanism
  let tokenRefreshTimer = null;

  async function refreshToken() {
    if (!authToken) {
      return false;
    }

    try {
      const response = await fetch('/api/token/refresh', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${authToken}`,
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });

      if (response.ok) {
        const data = await response.json();
        authToken = data.token;
        currentUser = data.user;
        localStorage.setItem('authToken', authToken);
        localStorage.setItem('userData', JSON.stringify(data.user));
        localStorage.setItem('tokenExpiresAt', data.expires_at);
        localStorage.setItem('tokenExpiresIn', data.expires_in);
        localStorage.setItem('rememberMe', data.remember);
        console.log('Token refreshed successfully');
        return true;
      } else {
        console.error('Token refresh failed:', response.status);
        if (response.status === 401) {
          // Token invalid - logout
          localStorage.removeItem('authToken');
          localStorage.removeItem('userData');
          localStorage.removeItem('tokenExpiresAt');
          localStorage.removeItem('tokenExpiresIn');
          localStorage.removeItem('rememberMe');
          authToken = null;
          currentUser = null;
          showLogin();
          showNotification('Session expired. Please login again.', 'warning');
        }
        return false;
      }
    } catch (error) {
      console.error('Token refresh error:', error);
      return false;
    }
  }

  function startTokenRefreshTimer() {
    if (tokenRefreshTimer) clearInterval(tokenRefreshTimer);

    const expiresAt = localStorage.getItem('tokenExpiresAt');
    if (!expiresAt) return;

    // Always derive remaining seconds from the stored ISO timestamp (not the stale tokenExpiresIn)
    const secsLeft = Math.max(0, (new Date(expiresAt) - Date.now()) / 1000);
    if (secsLeft === 0) return;

    const refreshThreshold = Math.min(300, secsLeft / 10); // 5 min or 10% of remaining life
    const checkInterval    = Math.max(60000, refreshThreshold * 500); // min 1 min

    tokenRefreshTimer = setInterval(async () => {
      const current = localStorage.getItem('tokenExpiresAt');
      if (!current) return;

      const timeUntilExpiry = (new Date(current) - Date.now()) / 1000;

      if (timeUntilExpiry < refreshThreshold && timeUntilExpiry > 0) {
        const refreshed = await refreshToken();
        if (refreshed) startTokenRefreshTimer();
      } else if (timeUntilExpiry <= 0) {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        localStorage.removeItem('tokenExpiresAt');
        localStorage.removeItem('tokenExpiresIn');
        localStorage.removeItem('rememberMe');
        authToken = null;
        currentUser = null;
        showLogin();
        showNotification('Session expired. Please login again.', 'warning');
        clearInterval(tokenRefreshTimer);
      }
    }, checkInterval);
  }

  // Validate session on page load
  async function validateSession() {
    if (!authToken) {
      showLogin();
      return;
    }

    // Show app immediately — avoids flash of login page while the /user call is in-flight
    showApp();

    // Proactively refresh if token expires within 24 hours so it never silently expires
    const expiresAt = localStorage.getItem('tokenExpiresAt');
    if (expiresAt) {
      const secsLeft = (new Date(expiresAt) - Date.now()) / 1000;
      if (secsLeft > 0 && secsLeft < 86400) {
        await refreshToken();
      }
    }

    try {
      const response = await apiCall('/user');
      if (!response.ok) {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        localStorage.removeItem('tokenExpiresAt');
        localStorage.removeItem('tokenExpiresIn');
        localStorage.removeItem('rememberMe');
        authToken = null;
        currentUser = null;
        showLogin();
        if (response.status === 401) {
          showNotification('Session expired. Please login again.', 'warning');
        }
        return;
      }
      startTokenRefreshTimer();
    } catch (error) {
      // Network error — stay optimistic, API calls will handle 401s
      startTokenRefreshTimer();
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
