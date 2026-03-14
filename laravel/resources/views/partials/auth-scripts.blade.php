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

    // Find all elements with permission requirements
    const permissionElements = document.querySelectorAll('[data-permission]');

    permissionElements.forEach(element => {
      const requiredPermission = element.getAttribute('data-permission');

      // Check if user has the required permission
      if (!hasPermission(requiredPermission)) {
        // Hide the element
        element.style.display = 'none';

        // Also disable it if it's an input/button to prevent keyboard access
        if (element.tagName === 'BUTTON' || element.tagName === 'INPUT' || element.tagName === 'A') {
          element.disabled = true;
          element.setAttribute('aria-hidden', 'true');
          element.tabIndex = -1;
        }
      } else {
        // Ensure it's visible and enabled
        element.style.display = '';

        if (element.tagName === 'BUTTON' || element.tagName === 'INPUT' || element.tagName === 'A') {
          element.disabled = false;
          element.removeAttribute('aria-hidden');
          element.tabIndex = 0;
        }
      }
    });

    // Also check for multiple permissions (any match)
    const anyPermissionElements = document.querySelectorAll('[data-permission-any]');

    anyPermissionElements.forEach(element => {
      const permissionsStr = element.getAttribute('data-permission-any');
      const permissions = permissionsStr.split(',').map(p => p.trim());

      // Check if user has ANY of the required permissions
      const hasAnyPermission = permissions.some(perm => hasPermission(perm));

      if (!hasAnyPermission) {
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

    // Check for elements requiring ALL permissions
    const allPermissionElements = document.querySelectorAll('[data-permission-all]');

    allPermissionElements.forEach(element => {
      const permissionsStr = element.getAttribute('data-permission-all');
      const permissions = permissionsStr.split(',').map(p => p.trim());

      // Check if user has ALL of the required permissions
      const hasAllPermissions = permissions.every(perm => hasPermission(perm));

      if (!hasAllPermissions) {
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

    // Create a MutationObserver to watch for DOM changes
    const observer = new MutationObserver((mutations) => {
      let shouldReapply = false;

      mutations.forEach((mutation) => {
        // Check if nodes were added
        if (mutation.addedNodes.length > 0) {
          mutation.addedNodes.forEach((node) => {
            // Check if the added node or its children have permission attributes
            if (node.nodeType === 1) { // Element node
              if (node.hasAttribute && (
                  node.hasAttribute('data-permission') ||
                  node.hasAttribute('data-permission-any') ||
                  node.hasAttribute('data-permission-all')
                )) {
                shouldReapply = true;
              } else if (node.querySelector) {
                const hasPermissionElements = node.querySelector('[data-permission], [data-permission-any], [data-permission-all]');
                if (hasPermissionElements) {
                  shouldReapply = true;
                }
              }
            }
          });
        }
      });

      // Reapply permissions if needed
      if (shouldReapply) {
        applyActionPermissions();
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
    // Clear existing timer
    if (tokenRefreshTimer) {
      clearInterval(tokenRefreshTimer);
    }

    const tokenExpiresIn = parseInt(localStorage.getItem('tokenExpiresIn') || '0');

    if (tokenExpiresIn > 0) {
      // Refresh token when it has 5 minutes left (or 1/10th of lifetime, whichever is less)
      const refreshThreshold = Math.min(300, tokenExpiresIn / 10); // 5 minutes or 10% of lifetime
      const checkInterval = Math.max(60000, refreshThreshold * 1000 / 2); // Check every minute or half the threshold

      console.log(`Token refresh timer started. Will check every ${checkInterval / 1000} seconds.`);

      tokenRefreshTimer = setInterval(async () => {
        const expiresAt = localStorage.getItem('tokenExpiresAt');
        if (!expiresAt) {
          return;
        }

        const expiresAtDate = new Date(expiresAt);
        const now = new Date();
        const timeUntilExpiry = (expiresAtDate - now) / 1000; // in seconds

        // Refresh if less than threshold seconds remaining
        if (timeUntilExpiry < refreshThreshold && timeUntilExpiry > 0) {
          console.log(`Token expiring in ${Math.floor(timeUntilExpiry)} seconds. Refreshing...`);
          const refreshed = await refreshToken();
          if (refreshed) {
            startTokenRefreshTimer(); // Restart timer with new expiration
          }
        } else if (timeUntilExpiry <= 0) {
          // Token already expired
          console.log('Token expired. Logging out...');
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
  }

  // Validate session on page load
  async function validateSession() {
    if (!authToken) {
      showLogin();
      return;
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
      showApp();
      startTokenRefreshTimer(); // Start automatic token refresh
    } catch (error) {
      // Network error - show app optimistically (API calls will handle 401s)
      showApp();
      startTokenRefreshTimer(); // Start automatic token refresh even on network error
    }
  }

  validateSession();

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
