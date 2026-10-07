<?php

use Illuminate\Support\Facades\Route;

// Login route (required by Laravel auth system)
Route::get('/login', function () {
    return view('dashboard'); // The dashboard view handles login UI
})->name('login');

// Password reset landing — the app layout's JS reads ?token & ?email from the
// query string, verifies via /api/password/verify-token, and opens the reset modal.
Route::get('/password/reset', function () {
    return view('dashboard');
})->name('password.reset');

Route::get('/', function () {
    return view('dashboard');
});

// Inventory Management
Route::get('/inventory/products', function () {
    return view('inventory.products');
});

Route::get('/categories', function () {
    return view('categories');
});

Route::get('/suppliers', function () {
    return view('suppliers');
});

Route::get('/low-stock', function () {
    return view('low-stock');
});

Route::get('/critical-stock', function () {
    return view('critical-stock');
});

// Operations
Route::get('/jobs', function () {
    return view('jobs');
});

Route::get('/purchase-orders', function () {
    return view('purchase-orders');
});

Route::get('/operations/replenishment', function () {
    return view('operations.replenishment');
});

Route::get('/cycle-counting', function () {
    return view('cycle-counting');
});

Route::get('/storage-locations', function () {
    return view('storage-locations');
});

Route::get('/transactions', function () {
    return view('transactions');
});

// Fulfillment
Route::get('/fulfillment/material-check', function () {
    return view('fulfillment.material-check');
});

Route::get('/fulfillment/job-reservations', function () {
    return view('fulfillment.job-reservations');
});

// Reports & Maintenance
Route::get('/reports/{report?}', function (?string $report = null) {
    abort_if($report !== null && ! collect(config('reports.reports'))->contains('key', $report), 404);

    return view('reports', ['initialReport' => $report]);
});

Route::get('/maintenance', function () {
    return view('maintenance');
});

// Fabrication
Route::get('/fabrication/documents', function () {
    return view('fabrication.documents');
});

Route::get('/fabrication/work-orders', function () {
    return view('fabrication.work-orders');
});

Route::get('/fabrication/cut-lists', function () {
    return view('fabrication.cut-lists');
});

Route::get('/fabrication/work-queue', function () {
    return view('fabrication.work-queue');
});

Route::get('/fabrication/quality', function () {
    return view('fabrication.quality');
});

// Configurator
Route::get('/config', function () {
    return view('configurator.frame');
});

Route::get('/config/package', function () {
    return view('configurator.package');
});

Route::get('/config/labels', function () {
    return view('configurator.labels');
});

Route::get('/config/admin', function () {
    return view('configurator.admin');
});

// System Status
Route::get('/status', function () {
    return view('status');
});

// Administration
Route::get('/admin', function () {
    return view('admin');
});

Route::get('/admin/location-assignment', function () {
    return view('admin.location-assignment');
});

// Shop floor — no auth required (tablet kiosk view)
Route::get('/shop', function () {
    return view('shop-floor');
});

// CutFlow — cut-station kiosk, no ForgeDesk auth required (same as /shop
// above). Operator identity is a fab_pin sign-in against FdUser inside the
// Livewire component itself, not a route-level guard.
// PWA plumbing (installable kiosk) — served by Laravel so the worker and
// manifest are scoped to /cut-station and never touch the rest of ForgeDesk.
Route::get('/cut-station/manifest.webmanifest', [\App\Http\Controllers\CutFlow\PwaController::class, 'manifest'])->name('cutflow.pwa.manifest');
Route::get('/cut-station/sw.js', [\App\Http\Controllers\CutFlow\PwaController::class, 'serviceWorker'])->name('cutflow.pwa.sw');
Route::get('/cut-station/offline', [\App\Http\Controllers\CutFlow\PwaController::class, 'offline'])->name('cutflow.pwa.offline');
Route::get('/cut-station', \App\Livewire\CutFlow\Dashboard::class)->name('cutflow.dashboard');
Route::get('/cut-station/import', [\App\Http\Controllers\CutFlow\ImportController::class, 'show'])->name('cutflow.import.show');
Route::post('/cut-station/import', [\App\Http\Controllers\CutFlow\ImportController::class, 'store'])->name('cutflow.import.store');
Route::get('/cut-station/settings', \App\Livewire\CutFlow\Settings::class)->name('cutflow.settings');
// The QR-scan cut record lives outside /cut-station so an installed kiosk app
// never captures it. Labels already printed point at the old path: redirect.
Route::get('/cut-record/{cutLogEntry:uuid}', [\App\Http\Controllers\CutFlow\CutController::class, 'show'])->name('cutflow.cuts.show');
Route::get('/cut-station/cuts/{uuid}', fn (string $uuid) => redirect()->route('cutflow.cuts.show', $uuid, 301))->name('cutflow.cuts.legacy');

// Design-time preview of the maintenance page, so it can be checked without
// actually toggling maintenance mode. Excluded entirely outside non-production
// so the route doesn't exist at all in a prod build, even if this file ships
// unchanged.
if (! app()->environment('production')) {
    Route::get('/dev/preview-503', fn () => response()->view('errors.503', [], 503));
}
