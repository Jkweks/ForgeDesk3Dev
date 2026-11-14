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
    // Office staff who only have the Project Management workspace land there.
    $u = auth()->user();
    if ($u && $u->hasPermission('nav.pm') && ! $u->hasAnyPermission(['nav.dashboard', 'nav.inventory', 'nav.fulfillment', 'nav.fabrication'])) {
        return redirect('/pm');
    }

    return view('dashboard');
});

// Project Management workspace (front of house)
// One shared dashboard: /pm renders the same customizable dashboard (and per-user layout) as /.
Route::get('/pm', fn () => view('dashboard'));
Route::get('/pm/jobs', fn () => view('pm.jobs'));
Route::get('/pm/jobs/{id}', fn (int $id) => view('pm.job', ['jobId' => $id]))->whereNumber('id');
Route::get('/pm/vendors', fn () => view('suppliers'));
Route::get('/pm/library', fn () => view('pm.library'));
Route::get('/pm/history', fn () => view('pm.history'));
Route::get('/pm/purchase-orders', fn () => view('pm.purchase-orders'));
Route::get('/pm/purchase-orders/{id}', fn (string $id) => view('pm.purchase-order', ['poId' => $id]))->where('id', 'new|[0-9]+');

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

// Field & Fleet Assets
Route::get('/fleet', function () {
    return view('fleet.index');
});
Route::get('/fleet/assets/{id}', function ($id) {
    return view('fleet.asset', ['assetId' => (int) $id]);
})->whereNumber('id');

// Shipments (pre/post-delivery inspections)
Route::get('/shipments', function () {
    return view('shipments.index');
});
Route::get('/shipments/queue', function () {
    return view('shipments.queue');
});
Route::get('/shipments/{id}', function ($id) {
    return view('shipments.show', ['shipmentId' => (int) $id]);
})->whereNumber('id');

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

Route::get('/fabrication/programming-queue', function () {
    return view('fabrication.programming-queue');
});

Route::get('/fabrication/quality', function () {
    return view('fabrication.quality');
});

// Configurator
Route::get('/config', function () {
    return view('configurator.frame', ['split' => true]);
});

Route::get('/config/classic', function () {
    return view('configurator.frame', ['split' => false]);
});

// Split is the default layout now; keep the old URL working.
Route::get('/config/split', function (\Illuminate\Http\Request $request) {
    return redirect('/config' . ($request->getQueryString() ? '?' . $request->getQueryString() : ''));
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

// Development tracker (admin only; the API enforces it, the page just renders the shell)
Route::get('/development', function () {
    return view('development');
});

// Administration
Route::get('/admin', function () {
    return view('admin');
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
