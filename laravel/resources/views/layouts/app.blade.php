<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ForgeDesk')</title>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-flags.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-socials.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-payments.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-vendors.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-marketing.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-themes.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
  <style>
    @import url("https://rsms.me/inter/inter.css");

    /* Dark mode compatible styles using Tabler CSS variables */
    .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    .table-actions { white-space: nowrap; }

    /* Login container - dark mode compatible gradient */
    .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--tblr-primary, #667eea) 0%, var(--tblr-purple, #764ba2) 100%);
    }

    #app { display: none; }
    #app.active { display: block; }
    #loginPage { display: none; }
    #loginPage.active { display: flex; }
    .loading { text-align: center; padding: 2rem; }

    /* Required field asterisk - uses theme danger color */
    .modal-body .form-label.required:after {
      content: " *";
      color: var(--tblr-danger, #d63939);
    }

    .alert.position-fixed {
      animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    /* Modal scrolling styles - dark mode compatible */
    .modal-content {
      max-height: 90vh;
      display: flex;
      flex-direction: column;
    }
    .modal-body {
      overflow-y: auto;
      max-height: calc(90vh - 120px);
      scrollbar-width: thin;
      scrollbar-color: var(--tblr-primary-lt, rgba(99, 102, 241, 0.5)) var(--tblr-border-color, rgba(98, 105, 118, 0.16));
    }
    .modal-body::-webkit-scrollbar {
      width: 8px;
    }
    .modal-body::-webkit-scrollbar-track {
      background: var(--tblr-border-color, rgba(98, 105, 118, 0.16));
      border-radius: 4px;
    }
    .modal-body::-webkit-scrollbar-thumb {
      background: var(--tblr-primary-lt, rgba(99, 102, 241, 0.5));
      border-radius: 4px;
      transition: background 0.2s ease;
    }
    .modal-body::-webkit-scrollbar-thumb:hover {
      background: var(--tblr-primary, rgba(99, 102, 241, 0.8));
    }
    .modal-header,
    .modal-footer {
      flex-shrink: 0;
    }

    /* iPad-specific modal widths - make modals wider on iPad devices */
    @media only screen
      and (min-width: 768px)
      and (max-width: 1024px) {
      /* Target all modal sizes on iPad */
      .modal-dialog {
        max-width: 90% !important;
      }

      .modal-dialog.modal-sm {
        max-width: 70% !important;
      }

      .modal-dialog.modal-lg {
        max-width: 90% !important;
      }

      .modal-dialog.modal-xl {
        max-width: 95% !important;
      }
    }

    /* iPad Pro specific adjustments */
    @media only screen
      and (min-width: 1024px)
      and (max-width: 1366px)
      and (-webkit-min-device-pixel-ratio: 1.5) {
      .modal-dialog {
        max-width: 85% !important;
      }

      .modal-dialog.modal-lg {
        max-width: 85% !important;
      }

      .modal-dialog.modal-xl {
        max-width: 90% !important;
      }
    }

    /* Dark mode badge improvements - better contrast and visibility */
    [data-bs-theme="dark"] .badge.bg-primary {
      background-color: #4299e1 !important;
      color: #1a202c !important;
    }
    [data-bs-theme="dark"] .badge.bg-success {
      background-color: #48bb78 !important;
      color: #1a202c !important;
    }
    [data-bs-theme="dark"] .badge.bg-info {
      background-color: #4299e1 !important;
      color: #1a202c !important;
    }
    [data-bs-theme="dark"] .badge.bg-warning {
      background-color: #ed8936 !important;
      color: #1a202c !important;
    }
    [data-bs-theme="dark"] .badge.bg-danger {
      background-color: #f56565 !important;
      color: #fff !important;
    }
    [data-bs-theme="dark"] .badge.bg-secondary {
      background-color: #718096 !important;
      color: #fff !important;
    }
    [data-bs-theme="dark"] .badge.bg-dark {
      background-color: #4a5568 !important;
      color: #fff !important;
    }

    /* Dark mode table row improvements - better visibility for shaded rows */
    [data-bs-theme="dark"] .table-success {
      background-color: rgba(72, 187, 120, 0.15) !important;
    }
    [data-bs-theme="dark"] .table-success td,
    [data-bs-theme="dark"] .table-success th {
      border-color: rgba(72, 187, 120, 0.25) !important;
    }

    [data-bs-theme="dark"] .table-warning {
      background-color: rgba(237, 137, 54, 0.15) !important;
    }
    [data-bs-theme="dark"] .table-warning td,
    [data-bs-theme="dark"] .table-warning th {
      border-color: rgba(237, 137, 54, 0.25) !important;
    }

    [data-bs-theme="dark"] .table-danger {
      background-color: rgba(245, 101, 101, 0.15) !important;
    }
    [data-bs-theme="dark"] .table-danger td,
    [data-bs-theme="dark"] .table-danger th {
      border-color: rgba(245, 101, 101, 0.25) !important;
    }

    [data-bs-theme="dark"] .table-secondary {
      background-color: rgba(113, 128, 150, 0.15) !important;
    }
    [data-bs-theme="dark"] .table-secondary td,
    [data-bs-theme="dark"] .table-secondary th {
      border-color: rgba(113, 128, 150, 0.25) !important;
    }

    [data-bs-theme="dark"] .table-info {
      background-color: rgba(66, 153, 225, 0.15) !important;
    }
    [data-bs-theme="dark"] .table-info td,
    [data-bs-theme="dark"] .table-info th {
      border-color: rgba(66, 153, 225, 0.25) !important;
    }

    @yield('styles')
  </style>
</head>
<body>
  <script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>

  <!-- Login Page -->
  <div id="loginPage" class="login-container">
    <div class="card" style="width: 100%; max-width: 400px;">
      <div class="card-body">
        <h2 class="text-center mb-4">ForgeDesk</h2>
        <form id="loginForm">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="loginEmail" value="admin@forgedesk.local" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" id="loginPassword" value="password" required>
          </div>
          <div class="mb-3">
            <label class="form-check">
              <input type="checkbox" class="form-check-input" id="loginRemember">
              <span class="form-check-label">Keep me logged in (30 days)</span>
            </label>
          </div>
          <div id="loginError" class="alert alert-danger" style="display: none;"></div>
          <button type="submit" class="btn btn-primary w-100">Login</button>
          <div class="text-center mt-3">
            <a href="#" id="forgotPasswordLink" class="text-muted">Forgot Password?</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
  <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Reset Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>
          <form id="forgotPasswordForm">
            <div class="mb-3">
              <label class="form-label">Email Address</label>
              <input type="email" class="form-control" id="forgotPasswordEmail" required>
            </div>
            <div id="forgotPasswordError" class="alert alert-danger" style="display: none;"></div>
            <div id="forgotPasswordSuccess" class="alert alert-success" style="display: none;"></div>
            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Set New Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="resetPasswordForm">
            <input type="hidden" id="resetToken">
            <input type="hidden" id="resetEmail">
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" id="newPassword" minlength="8" required>
              <small class="form-hint">Must be at least 8 characters long</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" id="confirmPassword" minlength="8" required>
            </div>
            <div id="resetPasswordError" class="alert alert-danger" style="display: none;"></div>
            <div id="resetPasswordSuccess" class="alert alert-success" style="display: none;"></div>
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Application -->
  <div id="app" class="page">
    @include('partials.header')
    @include('partials.navigation')

    <!-- Page Content -->
    @yield('content')

    <!-- Theme Settings (Available on all pages) -->
    @include('partials.theme-settings')
  </div>

  <!-- Scripts -->
  <script src="{{ asset('assets/tabler/js/tabler.min.js') }}"></script>

  @include('partials.auth-scripts')

  @stack('scripts')
</body>
</html>
