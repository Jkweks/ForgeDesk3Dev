<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ForgeDesk')</title>
  {{-- Applies the saved theme/layout attributes to <html> before first paint (no flash). --}}
  <script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-flags.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-socials.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-payments.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-vendors.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-marketing.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-themes.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.49.0/dist/tabler-icons.min.css" rel="stylesheet">
  <style>

    /* Dark mode compatible styles using Tabler CSS variables */
    .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    .table-actions { white-space: nowrap; }

    /* Login: centred card on the surface background, like the Tabler sign-in page */
    .login-container {
      min-height: 100vh;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background: var(--tblr-bg-surface-secondary);
    }

    /* Offset for page-level sticky elements (sidebars, mirror scrollbars): 0 normally; the top bar's
       height when the user chose a sticky top bar (Tabler makes only the header row sticky). */
    :root { --fd-nav-offset: 0px; }
    html[data-bs-navbar=sticky]:not([data-bs-navbar-position=vertical]) { --fd-nav-offset: 3.5rem; }

    #app { display: none; }
    #app.active { display: flex; flex-direction: column; }

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

    /* Modals: keep the header/footer in view and scroll the body, sized from the
       viewport minus Tabler's own modal margin (no magic numbers). */
    .modal-content { max-height: calc(100dvh - var(--tblr-modal-margin) * 2); }
    .modal-body { overflow-y: auto; scrollbar-width: thin; }
    .modal-header, .modal-footer { flex-shrink: 0; }

    /* Tablets: let modals use more of the width. Sets Tabler's width variable
       rather than overriding max-width. */
    @media (min-width: 768px) and (max-width: 1366px) and (pointer: coarse) {
      .modal-dialog { --tblr-modal-width: 90%; }
      .modal-dialog.modal-sm { --tblr-modal-width: 70%; }
      .modal-dialog.modal-xl { --tblr-modal-width: 95%; }
    }

    /* Solid badges: Tabler's bg-* utilities don't set text colour, so pair each
       with its theme foreground token (follows dark mode and the chosen primary). */
    .badge.bg-primary { color: var(--tblr-primary-fg); }
    .badge.bg-secondary { color: var(--tblr-secondary-fg); }
    .badge.bg-success { color: var(--tblr-success-fg); }
    .badge.bg-warning { color: var(--tblr-warning-fg); }
    .badge.bg-danger { color: var(--tblr-danger-fg); }
    .badge.bg-info { color: var(--tblr-info-fg); }
    .badge.bg-dark { color: var(--tblr-dark-fg); }
    .badge.bg-light { color: var(--tblr-light-fg); }
    .badge.bg-blue { color: var(--tblr-blue-fg); }
    .badge.bg-azure { color: var(--tblr-azure-fg); }
    .badge.bg-indigo { color: var(--tblr-indigo-fg); }
    .badge.bg-purple { color: var(--tblr-purple-fg); }
    .badge.bg-pink { color: var(--tblr-pink-fg); }
    .badge.bg-red { color: var(--tblr-red-fg); }
    .badge.bg-orange { color: var(--tblr-orange-fg); }
    .badge.bg-yellow { color: var(--tblr-yellow-fg); }
    .badge.bg-lime { color: var(--tblr-lime-fg); }
    .badge.bg-green { color: var(--tblr-green-fg); }
    .badge.bg-teal { color: var(--tblr-teal-fg); }
    .badge.bg-cyan { color: var(--tblr-cyan-fg); }

    @yield('styles')
  </style>
  @include('partials.fab-status-styles')
</head>
<body>
  <!-- Login Page -->
  <div id="loginPage" class="login-container">
    <div class="card card-md w-100" style="max-width: 24rem;">
      <div class="card-body">
        <div class="text-center mb-3">@include('partials.brand')</div>
        <h2 class="h2 text-center mb-4">Sign in to ForgeDesk</h2>
        <form id="loginForm">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="loginEmail" autocomplete="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" id="loginPassword" autocomplete="current-password" required>
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
            <input type="hidden" id="resetEmail" autocomplete="username">
            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" class="form-control" id="newPassword" minlength="8" autocomplete="new-password" required>
              <small class="form-text">Must be at least 8 characters long</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input type="password" class="form-control" id="confirmPassword" minlength="8" autocomplete="new-password" required>
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
    @include('partials.sidebar')
    @include('partials.navbar')

    <!-- Page Content (each view supplies its own .page-wrapper) -->
    @yield('content')

    <!-- Theme Settings (Available on all pages) -->
    @include('partials.theme-settings')
  </div>

  <!-- Scripts -->
  <script src="{{ asset('assets/tabler/js/tabler.min.js') }}"></script>
  <script>window.bootstrap = window.tabler;</script>
  <script src="{{ asset('js/fab-shared.js') }}"></script>

  @include('partials.auth-scripts')

  @stack('scripts')
</body>
</html>
