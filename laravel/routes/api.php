<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\BusinessJobController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CompanyLocationController;
use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\ConfiguratorCatalogController;
use App\Http\Controllers\Api\ConfiguratorDoorCatalogController;
use App\Http\Controllers\Api\ConfiguratorHwlibAdminController;
use App\Http\Controllers\Api\ConfiguratorHwlibCatalogController;
use App\Http\Controllers\Api\CycleCountController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoorFrameConfigurationController;
use App\Http\Controllers\Api\FabricationDocumentController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\InventoryLocationController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\JobReservationController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\MachineToolingController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use App\Http\Controllers\Api\MaintenanceTaskController;
use App\Http\Controllers\Api\MaterialCheckController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RequiredPartsController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public test route (no auth required)
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::get('/v1/test', function () {
    return response()->json([
        'message' => 'ForgeDesk API is working!',
        'version' => '1.0',
    ]);
});

// Public authentication routes
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'remember' => 'sometimes|boolean',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    if (! $user->is_active) {
        return response()->json(['message' => 'Your account has been deactivated. Please contact an administrator.'], 403);
    }

    // A temporary password that was never changed within the allowed window is dead.
    if ($user->temporaryPasswordExpired()) {
        return response()->json([
            'message' => 'Your temporary password has expired. Please ask an administrator to resend your invitation.',
        ], 403);
    }

    $user->updateLastLogin();

    $remember = $request->boolean('remember', false);
    \Illuminate\Support\Facades\Auth::login($user, $remember);
    $request->session()->regenerate();

    $permissions = [];
    if ($user->role) {
        $role = \App\Models\Role::where('name', $user->role)->first();
        if ($role) {
            $permissions = $role->permissions->pluck('name')->toArray();
        }
    }

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'permissions' => $permissions,
            'must_change_password' => $user->must_change_password,
            'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
        ],
    ]);
})->middleware('throttle:10,1');

Route::post('/logout', function (Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Logged out']);
})->middleware('auth:sanctum');

// Password Reset routes (public, CSRF-exempt — see bootstrap/app.php).
// Rate-limited since they are unauthenticated and send mail / write tokens.
Route::post('/password/forgot', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:6,1');
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:6,1');
Route::post('/password/verify-token', [PasswordResetController::class, 'verifyToken'])->middleware('throttle:30,1');

// Fulfillment routes (public for internal use)
Route::prefix('v1')->group(function () {
    // ── Shop floor (no auth — tablet kiosk) ──────────────────────────────────
    Route::get('/shop/work-orders', [\App\Http\Controllers\Api\ShopFloorController::class, 'workOrders']);
    Route::get('/shop/my-queue', [\App\Http\Controllers\Api\ShopFloorController::class, 'myQueue'])->middleware('throttle:120,1');
    Route::get('/shop/fab-users', [\App\Http\Controllers\Api\ShopFloorController::class, 'fabUsers']);
    // PIN login is unauthenticated and checks every fab user's hash — throttle
    // hard to keep it from being brute-forced.
    Route::post('/shop/fab-pin-login', [\App\Http\Controllers\Api\ShopFloorController::class, 'pinLogin'])->middleware('throttle:10,1');
    // Kiosk stage mutations stay unauthenticated (shared tablets) but are
    // throttled so a stray script can't run away with production state.
    Route::patch('/shop/stages/{id}', [\App\Http\Controllers\Api\ShopFloorController::class, 'cycleStage'])->middleware('throttle:120,1');
    Route::patch('/shop/stages/{id}/status', [\App\Http\Controllers\Api\ShopFloorController::class, 'setStageStatus'])->middleware('throttle:120,1');
    Route::patch('/shop/stages/{id}/assign', [\App\Http\Controllers\Api\ShopFloorController::class, 'assignStage'])->middleware('throttle:120,1');
    Route::patch('/shop/elevations/{id}', [\App\Http\Controllers\Api\ShopFloorController::class, 'updateElevation'])->middleware('throttle:120,1');
    Route::patch('/shop/elevations/{id}/complete-stages', [\App\Http\Controllers\Api\ShopFloorController::class, 'bulkCompleteStages'])->middleware('throttle:120,1');
    Route::patch('/shop/work-orders/{id}/stages/bulk-complete', [\App\Http\Controllers\Api\ShopFloorController::class, 'bulkCompleteWoStage'])->middleware('throttle:120,1');
    // ─────────────────────────────────────────────────────────────────────────

    Route::get('/fulfillment/test', [MaterialCheckController::class, 'test']);
    Route::post('/fulfillment/material-check', [MaterialCheckController::class, 'checkMaterials']);
    Route::post('/fulfillment/material-check-csv', [MaterialCheckController::class, 'checkCsv']);
    // Commit-to-job creates a live (active) reservation and moves inventory —
    // unlike the read-only material checks above it must be authenticated and
    // permission-gated.
    Route::post('/fulfillment/commit-materials', [MaterialCheckController::class, 'commitMaterials'])
        ->middleware(['auth:sanctum', 'permission:jobs.manage-reservations']);

    // Job Reservations — authenticated. Reads need jobs.view, writes need
    // jobs.manage-reservations (the same mapping as /business-jobs/{id}/reservations).
    // admin/manager/fabricator hold both, so their workflow is unchanged; this
    // only closes the endpoints to anonymous callers, viewers and office staff.
    // IMPORTANT: specific routes MUST come before parameterized routes like {id}.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/job-reservations', [JobReservationController::class, 'index'])->middleware('permission:reservations.dashboard.view');
        Route::post('/job-reservations/create-manual', [JobReservationController::class, 'createManual'])->middleware('permission:jobs.manage-reservations');
        Route::get('/job-reservations/search-product', [JobReservationController::class, 'searchProduct'])->middleware('permission:jobs.view');
        Route::get('/job-reservations/search-products', [JobReservationController::class, 'searchProducts'])->middleware('permission:jobs.view');
        Route::get('/job-reservations/status-labels', [JobReservationController::class, 'statusLabels'])->middleware('permission:jobs.view');
        Route::get('/job-reservations/{id}', [JobReservationController::class, 'show'])->middleware('permission:jobs.view');
        Route::put('/job-reservations/{id}', [JobReservationController::class, 'updateReservation'])->middleware('permission:jobs.manage-reservations');
        Route::post('/job-reservations/{id}/status', [JobReservationController::class, 'updateStatus'])->middleware('permission:jobs.manage-reservations');
        Route::post('/job-reservations/{id}/complete', [JobReservationController::class, 'complete'])->middleware('permission:jobs.manage-reservations');
        Route::post('/job-reservations/{id}/items', [JobReservationController::class, 'addItem'])->middleware('permission:jobs.manage-reservations');
        Route::put('/job-reservations/{id}/items/{itemId}', [JobReservationController::class, 'updateItem'])->middleware('permission:jobs.manage-reservations');
        Route::post('/job-reservations/{id}/items/{itemId}/replace', [JobReservationController::class, 'replaceItem'])->middleware('permission:jobs.manage-reservations');
        Route::delete('/job-reservations/{id}/items/{itemId}', [JobReservationController::class, 'removeItem'])->middleware('permission:jobs.manage-reservations');
    });

    // EZ Estimate Management (admin web interface). Login required; upload is a
    // file write and the debug endpoints dump parsed data.
    Route::get('/ez-estimate/test', [\App\Http\Controllers\Api\EzEstimateController::class, 'test']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/ez-estimate/debug', [\App\Http\Controllers\Api\EzEstimateController::class, 'debug']);
        Route::get('/ez-estimate/test-pricing', [\App\Http\Controllers\Api\EzEstimateController::class, 'testPricing']);
        Route::post('/ez-estimate/upload', [\App\Http\Controllers\Api\EzEstimateController::class, 'upload'])->middleware('throttle:20,1');
        Route::get('/ez-estimate/current-file', [\App\Http\Controllers\Api\EzEstimateController::class, 'getCurrentFile']);
        Route::get('/ez-estimate/stats', [\App\Http\Controllers\Api\EzEstimateController::class, 'getStats']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->middleware('password.current')->group(function () {
        // Current user
        Route::get('/user', function (Request $request) {
            $user = $request->user();
            $permissions = [];
            if ($user->role) {
                $role = \App\Models\Role::where('name', $user->role)->first();
                if ($role) {
                    $permissions = $role->permissions->pluck('name')->toArray();
                }
            }

            return [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'permissions' => $permissions,
                'must_change_password' => $user->must_change_password,
                'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
                'theme_preferences' => $user->theme_preferences,
                'wo_column_prefs' => $user->wo_column_prefs,
                'quality_report_prefs' => $user->quality_report_prefs,
            ];
        });

        // People picker (Requested by / Project manager) — any signed-in user.
        Route::get('/people', [\App\Http\Controllers\Api\UserController::class, 'people']);

        // User Management
        Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index'])->middleware('permission:users.view');
        Route::get('/users/statistics', [\App\Http\Controllers\Api\UserController::class, 'statistics'])->middleware('permission:users.view');
        // Static path before the /users/{user} wildcard.
        Route::post('/users/send-pending-invitations', [\App\Http\Controllers\Api\UserController::class, 'sendPendingInvitations'])->middleware('permission:users.create');
        Route::get('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'show'])->middleware('permission:users.view');
        Route::post('/users', [\App\Http\Controllers\Api\UserController::class, 'store'])->middleware('permission:users.create');
        Route::put('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'update'])->middleware('permission:users.edit');
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'destroy'])->middleware('permission:users.delete');
        Route::post('/users/{user}/restore', [\App\Http\Controllers\Api\UserController::class, 'restore'])->middleware('permission:users.delete');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Api\UserController::class, 'resetPassword'])->middleware('permission:users.edit');
        Route::post('/users/{user}/resend-invitation', [\App\Http\Controllers\Api\UserController::class, 'resendInvitation'])->middleware('permission:users.edit');

        // Self-service user endpoints
        Route::post('/user/change-password', [\App\Http\Controllers\Api\UserController::class, 'changePassword']);
        Route::put('/user/profile', [\App\Http\Controllers\Api\UserController::class, 'updateProfile']);
        Route::put('/user/theme-preferences', [\App\Http\Controllers\Api\UserController::class, 'updateThemePreferences']);
        Route::put('/user/wo-column-prefs', [\App\Http\Controllers\Api\UserController::class, 'updateWoColumnPrefs']);
        Route::put('/user/quality-report-prefs', [\App\Http\Controllers\Api\UserController::class, 'updateQualityReportPrefs']);

        // Role & Permission Management
        Route::get('/roles', [\App\Http\Controllers\Api\RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::get('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::post('/roles', [\App\Http\Controllers\Api\RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'update'])->middleware('permission:roles.edit');
        Route::delete('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        Route::get('/permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions'])->middleware('permission:roles.view');
        Route::post('/roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'assignPermissions'])->middleware('permission:roles.edit');

        // System Status
        Route::get('/status', [StatusController::class, 'index']);

        // System Notifications (nav bar bell, admin-only — see NotificationController)
        Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/dismiss', [\App\Http\Controllers\Api\NotificationController::class, 'dismiss']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/inventory/{status}', [DashboardController::class, 'inventoryByStatus']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        // Categories
        Route::apiResource('categories', CategoryController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory.view')
            ->middlewareFor('store', 'permission:inventory.create')
            ->middlewareFor('update', 'permission:inventory.edit')
            ->middlewareFor('destroy', 'permission:inventory.delete');
        Route::get('/categories-tree', [CategoryController::class, 'tree']);
        Route::get('/category-systems', [CategoryController::class, 'systems']);
        Route::post('/categories/sort-order', [CategoryController::class, 'updateSortOrder']);
        Route::post('/categories/bulk-action', [CategoryController::class, 'bulkAction']);

        // Suppliers
        Route::apiResource('suppliers', SupplierController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory.view')
            ->middlewareFor('store', 'permission:inventory.create')
            ->middlewareFor('update', 'permission:inventory.edit')
            ->middlewareFor('destroy', 'permission:inventory.delete');
        // Company locations (the buying entity's own addresses; primary = PO order-from)
        Route::get('/company-locations', [CompanyLocationController::class, 'index'])->middleware('permission:settings.view');
        Route::post('/company-locations', [CompanyLocationController::class, 'store'])->middleware('permission:settings.edit');
        Route::patch('/company-locations/{companyLocation}', [CompanyLocationController::class, 'update'])->middleware('permission:settings.edit');
        Route::delete('/company-locations/{companyLocation}', [CompanyLocationController::class, 'destroy'])->middleware('permission:settings.edit');

        // Company-wide settings (branding)
        Route::get('/company-settings', [CompanySettingController::class, 'show'])->middleware('permission:settings.view');
        Route::post('/company-settings/logo', [CompanySettingController::class, 'uploadLogo'])->middleware('permission:settings.edit');
        Route::delete('/company-settings/logo', [CompanySettingController::class, 'deleteLogo'])->middleware('permission:settings.edit');

        Route::get('/supplier-countries', [SupplierController::class, 'countries']);
        Route::get('/supplier-statistics', [SupplierController::class, 'statistics']);
        Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products']);
        Route::get('/suppliers/{supplier}/contacts', [SupplierController::class, 'contacts']);
        Route::get('/suppliers/{supplier}/low-stock-report', [SupplierController::class, 'lowStockReport']);
        Route::post('/suppliers/bulk-action', [SupplierController::class, 'bulkAction']);

        // Products
        Route::apiResource('products', ProductController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory.view')
            ->middlewareFor('store', 'permission:inventory.create')
            ->middlewareFor('update', 'permission:inventory.edit')
            ->middlewareFor('destroy', 'permission:inventory.delete');
        Route::post('/products/refresh-statuses', [ProductController::class, 'refreshAllStatuses']);
        Route::put('/products/{product}/configurator-specs', [ProductController::class, 'updateConfiguratorSpecs'])->middleware('permission:inventory.edit');
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustInventory'])->middleware('permission:inventory.adjust');
        Route::post('/products/{product}/issue-to-job', [ProductController::class, 'issueToJob']);
        Route::get('/products/{product}/transactions', [ProductController::class, 'getTransactions']);
        Route::get('/products/{product}/calculate-reorder', [ProductController::class, 'calculateReorderPoint']);
        Route::post('/products/{product}/photo', [ProductController::class, 'uploadPhoto']);
        Route::delete('/products/{product}/photo', [ProductController::class, 'deletePhoto']);
        Route::get('/finish-codes', [ProductController::class, 'getFinishCodes']);
        Route::get('/unit-of-measures', [ProductController::class, 'getUnitOfMeasures']);

        // Inventory Locations
        Route::get('/products/{product}/locations', [InventoryLocationController::class, 'index']);
        Route::post('/products/{product}/locations', [InventoryLocationController::class, 'store']);
        Route::put('/products/{product}/locations/{location}', [InventoryLocationController::class, 'update']);
        Route::delete('/products/{product}/locations/{location}', [InventoryLocationController::class, 'destroy']);
        Route::post('/products/{product}/locations/transfer', [InventoryLocationController::class, 'transfer']);
        Route::post('/products/{product}/locations/{location}/adjust', [InventoryLocationController::class, 'adjust'])->middleware('permission:inventory.adjust');
        Route::get('/products/{product}/locations/statistics', [InventoryLocationController::class, 'statistics']);
        Route::get('/locations', [InventoryLocationController::class, 'getAllLocations']);
        Route::get('/locations/by-storage/{storageLocation}', [InventoryLocationController::class, 'itemsAtLocation']);
        Route::get('/locations/products-without-storage', [InventoryLocationController::class, 'productsWithoutStorageLocation']);

        // Storage Locations (Master Location Management)
        Route::get('/storage-locations-tree', [App\Http\Controllers\Api\StorageLocationController::class, 'tree']);
        Route::get('/storage-locations-stats', [App\Http\Controllers\Api\StorageLocationController::class, 'withStats']);
        Route::get('/storage-locations-names', [App\Http\Controllers\Api\StorageLocationController::class, 'locationNames']);
        Route::get('/storage-locations/bulk-shelf-labels-pdf', [App\Http\Controllers\Api\StorageLocationController::class, 'bulkShelfLabelsPdf']);
        Route::get('/storage-locations/{storageLocation}/shelf-label-pdf', [App\Http\Controllers\Api\StorageLocationController::class, 'shelfLabelPdf']);
        Route::apiResource('storage-locations', App\Http\Controllers\Api\StorageLocationController::class)
            ->middlewareFor(['index', 'show'], 'permission:inventory.view')
            ->middlewareFor('store', 'permission:inventory.create')
            ->middlewareFor('update', 'permission:inventory.edit')
            ->middlewareFor('destroy', 'permission:inventory.delete');

        // Job Reservations
        Route::get('/products/{product}/reservations', [JobReservationController::class, 'index']);
        Route::get('/products/{product}/reservations/active', [JobReservationController::class, 'active']);
        Route::post('/products/{product}/reservations', [JobReservationController::class, 'store']);
        Route::put('/products/{product}/reservations/{reservation}', [JobReservationController::class, 'update']);
        Route::post('/products/{product}/reservations/{reservation}/fulfill', [JobReservationController::class, 'fulfill']);
        Route::post('/products/{product}/reservations/{reservation}/release', [JobReservationController::class, 'release']);
        Route::delete('/products/{product}/reservations/{reservation}', [JobReservationController::class, 'destroy']);
        Route::get('/products/{product}/reservations/statistics', [JobReservationController::class, 'statistics']);
        Route::get('/jobs', [JobReservationController::class, 'getAllJobs']);

        // Inventory Transactions (Activity & Audit Trail)
        Route::get('/transactions', [InventoryTransactionController::class, 'index']);
        Route::post('/transactions/manual', [InventoryTransactionController::class, 'createManual'])->middleware('permission:inventory.create');
        Route::get('/transactions/{transaction}', [InventoryTransactionController::class, 'show']);
        Route::match(['put', 'patch'], '/transactions/{transaction}', [InventoryTransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [InventoryTransactionController::class, 'destroy']);
        Route::get('/transactions-statistics', [InventoryTransactionController::class, 'statistics']);
        Route::get('/transactions-types', [InventoryTransactionController::class, 'types']);
        Route::get('/transactions-export', [InventoryTransactionController::class, 'export']);
        Route::get('/transactions-recent', [InventoryTransactionController::class, 'recentActivity']);
        Route::get('/transactions-timeline', [InventoryTransactionController::class, 'timeline']);
        Route::get('/products/{product}/transactions', [InventoryTransactionController::class, 'productTransactions']);

        // Configurator & BOM (Required Parts)
        Route::get('/products/{product}/required-parts', [RequiredPartsController::class, 'index']);
        Route::post('/products/{product}/required-parts', [RequiredPartsController::class, 'store']);
        Route::put('/products/{product}/required-parts/{requiredPart}', [RequiredPartsController::class, 'update']);
        Route::delete('/products/{product}/required-parts/{requiredPart}', [RequiredPartsController::class, 'destroy']);
        Route::get('/products/{product}/bom-explosion', [RequiredPartsController::class, 'explosion']);
        Route::get('/products/{product}/bom-availability', [RequiredPartsController::class, 'checkAvailability']);
        Route::post('/products/{product}/required-parts/sort-order', [RequiredPartsController::class, 'updateSortOrder']);
        Route::get('/products/{product}/where-used', [RequiredPartsController::class, 'whereUsed']);

        // Reports & Analytics — all require reports.view; export/PDF/CSV routes
        // additionally require reports.export.
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports/low-stock', [ReportsController::class, 'lowStockReport']);
            Route::get('/reports/committed-parts', [ReportsController::class, 'committedPartsReport']);
            Route::get('/reports/velocity', [ReportsController::class, 'stockVelocityAnalysis']);
            Route::get('/reports/reorder-recommendations', [ReportsController::class, 'reorderRecommendations']);
            Route::get('/reports/obsolete', [ReportsController::class, 'obsoleteInventory']);
            Route::get('/reports/usage-analytics', [ReportsController::class, 'usageAnalytics']);
            Route::get('/reports/monthly-statement', [ReportsController::class, 'monthlyInventoryStatement']);
            Route::get('/reports/inventory/data', [ReportsController::class, 'inventoryReportData']);
            Route::get('/reports/storage-locations', [ReportsController::class, 'storageLocationReport']);
            Route::get('/reports/work-order-backlog', [ReportsController::class, 'workOrderBacklogReport']);
            Route::get('/reports/job-status-summary', [ReportsController::class, 'jobStatusSummaryReport']);
            Route::get('/reports/joints-completed', [ReportsController::class, 'jointsCompletedReport']);

            // Exports / PDF / CSV
            Route::middleware('permission:reports.export')->group(function () {
                Route::get('/reports/export', [ReportsController::class, 'exportReport']);
                Route::get('/reports/low-stock/pdf', [ReportsController::class, 'lowStockPdf']);
                Route::get('/reports/committed-parts/pdf', [ReportsController::class, 'committedPartsPdf']);
                Route::get('/reports/velocity/pdf', [ReportsController::class, 'velocityAnalysisPdf']);
                Route::get('/reports/reorder-recommendations/pdf', [ReportsController::class, 'reorderRecommendationsPdf']);
                Route::get('/reports/obsolete/pdf', [ReportsController::class, 'obsoleteInventoryPdf']);
                Route::get('/reports/usage-analytics/pdf', [ReportsController::class, 'usageAnalyticsPdf']);
                Route::get('/reports/monthly-statement/pdf', [ReportsController::class, 'monthlyInventoryStatementPdf']);
                Route::get('/reports/inventory/csv', [ReportsController::class, 'exportInventoryCsv']);
                Route::get('/reports/inventory/pdf', [ReportsController::class, 'inventoryReportPdf']);
                Route::get('/reports/storage-locations/pdf', [ReportsController::class, 'storageLocationPdf']);
                Route::get('/reports/work-order-backlog/pdf', [ReportsController::class, 'workOrderBacklogPdf']);
                Route::get('/reports/job-status-summary/pdf', [ReportsController::class, 'jobStatusSummaryPdf']);
                Route::get('/reports/joints-completed/pdf', [ReportsController::class, 'jointsCompletedPdf']);
            });
        });

        // Purchase Orders
        Route::apiResource('purchase-orders', PurchaseOrderController::class)
            ->middlewareFor(['index', 'show'], 'permission:orders.view')
            ->middlewareFor('store', 'permission:orders.create')
            ->middlewareFor('update', 'permission:orders.edit')
            ->middlewareFor('destroy', 'permission:orders.delete');
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->middleware('permission:orders.submit');
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:orders.approve');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:orders.receive');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:orders.edit');
        Route::post('/purchase-orders/{purchaseOrder}/items', [PurchaseOrderController::class, 'addItem'])->middleware('permission:orders.edit');
        Route::patch('/purchase-orders/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'updateItem'])->middleware('permission:orders.edit');
        Route::delete('/purchase-orders/{purchaseOrder}/items/{item}', [PurchaseOrderController::class, 'removeItem'])->middleware('permission:orders.edit');
        Route::get('/purchase-orders/{purchaseOrder}/ez-estimate-export', [PurchaseOrderController::class, 'exportEzEstimate']);
        Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'exportPdf'])->middleware('permission:orders.view');
        Route::get('/purchase-orders-open', [PurchaseOrderController::class, 'open']);
        Route::get('/purchase-orders-statistics', [PurchaseOrderController::class, 'statistics']);
        Route::get('/purchase-orders-eligible-approvers', [PurchaseOrderController::class, 'eligibleApprovers'])->middleware('permission:orders.edit');

        // Cycle Counting
        Route::apiResource('cycle-counts', CycleCountController::class)
            ->middlewareFor(['index', 'show'], 'permission:cycle-count.view')
            ->middlewareFor('store', 'permission:cycle-count.create');
        Route::post('/cycle-counts/{cycleCountSession}/start', [CycleCountController::class, 'start'])->middleware('permission:cycle-count.create');
        Route::post('/cycle-counts/{cycleCountSession}/record-count', [CycleCountController::class, 'recordCount'])->middleware('permission:cycle-count.record');
        Route::post('/cycle-counts/{cycleCountSession}/approve-variances', [CycleCountController::class, 'approveVariances'])->middleware('permission:cycle-count.approve');
        Route::post('/cycle-counts/{cycleCountSession}/complete', [CycleCountController::class, 'complete'])->middleware('permission:cycle-count.complete');
        Route::post('/cycle-counts/{cycleCountSession}/cancel', [CycleCountController::class, 'cancel'])->middleware('permission:cycle-count.cancel');
        Route::get('/cycle-counts/{cycleCountSession}/variance-report', [CycleCountController::class, 'varianceReport'])->middleware('permission:cycle-count.view');
        Route::get('/cycle-counts/{cycleCountSession}/pdf', [CycleCountController::class, 'generatePdf'])->middleware('permission:cycle-count.export');
        Route::get('/cycle-counts-active', [CycleCountController::class, 'active']);
        Route::get('/cycle-counts-statistics', [CycleCountController::class, 'statistics']);

        // Orders
        Route::apiResource('orders', OrderController::class)
            ->middlewareFor(['index', 'show'], 'permission:orders.view')
            ->middlewareFor('store', 'permission:orders.create')
            ->middlewareFor('update', 'permission:orders.edit')
            ->middlewareFor('destroy', 'permission:orders.delete');
        Route::post('/orders/{order}/commit', [OrderController::class, 'commitInventory'])->middleware('permission:orders.edit');
        Route::post('/orders/{order}/release', [OrderController::class, 'releaseInventory'])->middleware('permission:orders.edit');
        Route::post('/orders/{order}/ship', [OrderController::class, 'shipOrder'])->middleware('permission:orders.edit');

        // Import/Export
        Route::post('/import/products', [ImportExportController::class, 'importProducts']);
        Route::get('/export/products', [ImportExportController::class, 'exportProducts']);
        Route::get('/export/template', [ImportExportController::class, 'downloadTemplate']);

        // Maintenance
        Route::get('/maintenance/dashboard', [MaintenanceController::class, 'dashboard'])->middleware('permission:maintenance.view');
        Route::get('/maintenance/upcoming-tasks', [MaintenanceController::class, 'upcomingTasks'])->middleware('permission:maintenance.view');
        Route::get('/maintenance/recent-records', [MaintenanceController::class, 'recentRecords'])->middleware('permission:maintenance.view');
        Route::get('/maintenance/consumables', [MaintenanceController::class, 'consumables'])->middleware('permission:maintenance.view');
        Route::get('/maintenance/service-history/pdf', [MaintenanceController::class, 'serviceHistoryPdf'])->middleware('permission:maintenance.view');

        // Machines
        Route::apiResource('machines', MachineController::class)
            ->middlewareFor(['index', 'show'], 'permission:maintenance.view')
            ->middlewareFor(['store', 'update', 'destroy'], 'permission:maintenance.manage');
        Route::get('/machine-types', [MachineController::class, 'getTypes'])->middleware('permission:maintenance.view');

        // Assets
        Route::apiResource('assets', AssetController::class)
            ->middlewareFor(['index', 'show'], 'permission:maintenance.view')
            ->middlewareFor(['store', 'update', 'destroy'], 'permission:maintenance.manage');

        // Maintenance Tasks
        Route::apiResource('maintenance-tasks', MaintenanceTaskController::class)
            ->middlewareFor(['index', 'show'], 'permission:maintenance.view')
            ->middlewareFor(['store', 'update', 'destroy'], 'permission:maintenance.manage');

        // Maintenance Records
        Route::apiResource('maintenance-records', MaintenanceRecordController::class)
            ->middlewareFor(['index', 'show'], 'permission:maintenance.view')
            ->middlewareFor(['store', 'update', 'destroy'], 'permission:maintenance.manage');

        // Machine Tooling
        Route::get('/machine-tooling/inventory', [MachineToolingController::class, 'inventory'])->middleware('permission:maintenance.view');
        Route::get('/machine-tooling/all', [MachineToolingController::class, 'all'])->middleware('permission:maintenance.view');
        Route::get('/machine-tooling/statistics', [MachineToolingController::class, 'statistics'])->middleware('permission:maintenance.view');
        Route::get('/machine-tooling/tool-life-units', [MachineToolingController::class, 'toolLifeUnits'])->middleware('permission:maintenance.view');
        Route::get('/machine-tooling/tool-types', [MachineToolingController::class, 'toolTypes'])->middleware('permission:maintenance.view');
        Route::get('/machines/{machine}/tooling', [MachineToolingController::class, 'index'])->middleware('permission:maintenance.view');
        Route::post('/machines/{machine}/tooling', [MachineToolingController::class, 'store'])->middleware('permission:maintenance.manage');
        Route::get('/machines/{machine}/tooling/compatible-tools', [MachineToolingController::class, 'compatibleTools'])->middleware('permission:maintenance.view');
        Route::get('/machine-tooling/{id}', [MachineToolingController::class, 'show'])->middleware('permission:maintenance.view');
        Route::put('/machine-tooling/{id}/update-life', [MachineToolingController::class, 'updateToolLife'])->middleware('permission:maintenance.manage');
        Route::post('/machine-tooling/{id}/replace', [MachineToolingController::class, 'replace'])->middleware('permission:maintenance.manage');
        Route::post('/machine-tooling/{id}/remove', [MachineToolingController::class, 'remove'])->middleware('permission:maintenance.manage');

        // Business Jobs (Project Management)
        Route::apiResource('business-jobs', BusinessJobController::class)
            ->middlewareFor(['index', 'show'], 'permission:jobs.view')
            ->middlewareFor('store', 'permission:jobs.create')
            ->middlewareFor('update', 'permission:jobs.edit')
            ->middlewareFor('destroy', 'permission:jobs.delete');

        // Job-specific Work Orders (Fabrication)
        Route::get('/business-jobs/{jobId}/work-orders', [BusinessJobController::class, 'getWorkOrders'])->middleware('permission:jobs.view');

        // Job-specific Transactions
        Route::get('/business-jobs/{jobId}/transactions', [BusinessJobController::class, 'getTransactions'])->middleware('permission:jobs.view');
        Route::post('/business-jobs/{jobId}/transactions', [BusinessJobController::class, 'createTransaction'])->middleware('permission:jobs.manage-transactions');

        // Job-specific Reservations
        Route::get('/business-jobs/{jobId}/reservations', [BusinessJobController::class, 'getReservations'])->middleware('permission:jobs.view');
        Route::post('/business-jobs/{jobId}/reservations', [BusinessJobController::class, 'createReservation'])->middleware('permission:jobs.manage-reservations');
        Route::get('/business-jobs/{jobId}/reservations/{reservationId}', [BusinessJobController::class, 'getReservation'])->middleware('permission:jobs.view');
        Route::post('/business-jobs/{jobId}/reservations/{reservationId}/status', [BusinessJobController::class, 'updateReservationStatus'])->middleware('permission:jobs.manage-reservations');
        Route::delete('/business-jobs/{jobId}/reservations/{reservationId}', [BusinessJobController::class, 'deleteReservation'])->middleware('permission:jobs.manage-reservations');

        // Job-specific Documents (SOF / EZ Estimate / PO / Other — storage only)
        Route::get('/business-jobs/{jobId}/documents', [\App\Http\Controllers\Api\JobDocumentController::class, 'index'])->middleware('permission:jobs.documents.view');
        Route::get('/business-jobs/{jobId}/documents/{documentId}/download', [\App\Http\Controllers\Api\JobDocumentController::class, 'download'])->middleware('permission:jobs.documents.view');
        Route::post('/business-jobs/{jobId}/documents', [\App\Http\Controllers\Api\JobDocumentController::class, 'store'])->middleware('permission:jobs.documents.manage');
        Route::delete('/business-jobs/{jobId}/documents/{documentId}', [\App\Http\Controllers\Api\JobDocumentController::class, 'destroy'])->middleware('permission:jobs.documents.manage');

        // Door/Frame Configurator
        Route::get('/door-frame-configurations', [DoorFrameConfigurationController::class, 'index'])->middleware('permission:configurator.view');
        Route::post('/door-frame-configurations', [DoorFrameConfigurationController::class, 'store'])->middleware('permission:configurator.edit');
        Route::get('/door-frame-configurations/{id}', [DoorFrameConfigurationController::class, 'show'])->middleware('permission:configurator.view');
        Route::post('/door-frame-configurations/{id}/duplicate', [DoorFrameConfigurationController::class, 'duplicate'])->middleware('permission:configurator.edit');
        Route::post('/door-frame-configurations/{id}/unlink', [DoorFrameConfigurationController::class, 'unlink'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/opening-specs', [DoorFrameConfigurationController::class, 'updateOpeningSpecs'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/frame-config', [DoorFrameConfigurationController::class, 'updateFrameConfig'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/frame-parts', [DoorFrameConfigurationController::class, 'updateFrameParts'])->middleware('permission:configurator.edit');
        Route::post('/door-frame-configurations/{id}/frame-parts/generate', [DoorFrameConfigurationController::class, 'generateFrameParts'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/frame-parts/{partId}', [DoorFrameConfigurationController::class, 'updateFramePart'])->middleware('permission:configurator.edit');
        Route::delete('/door-frame-configurations/{id}/frame-parts/{partId}', [DoorFrameConfigurationController::class, 'destroyFramePart'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/door-config', [DoorFrameConfigurationController::class, 'updateDoorConfig'])->middleware('permission:configurator.edit');
        Route::post('/door-frame-configurations/{id}/door-parts/generate', [DoorFrameConfigurationController::class, 'generateDoorParts'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/door-parts/{partId}', [DoorFrameConfigurationController::class, 'updateDoorPart'])->middleware('permission:configurator.edit');
        Route::delete('/door-frame-configurations/{id}/door-parts/{partId}', [DoorFrameConfigurationController::class, 'destroyDoorPart'])->middleware('permission:configurator.edit');

        // Hardware library links + BOM
        Route::post('/door-frame-configurations/{id}/hardware-links', [DoorFrameConfigurationController::class, 'addHardwareLink'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/hardware-links/{linkId}', [DoorFrameConfigurationController::class, 'updateHardwareLink'])->middleware('permission:configurator.edit');
        Route::delete('/door-frame-configurations/{id}/hardware-links/{linkId}', [DoorFrameConfigurationController::class, 'destroyHardwareLink'])->middleware('permission:configurator.edit');
        Route::get('/door-frame-configurations/{id}/hardware-values', [DoorFrameConfigurationController::class, 'resolvedHardwareValues'])->middleware('permission:configurator.view');
        Route::post('/door-frame-configurations/{id}/hardware-parts/generate', [DoorFrameConfigurationController::class, 'generateHardwareParts'])->middleware('permission:configurator.edit');
        Route::put('/door-frame-configurations/{id}/hardware-parts/{partId}', [DoorFrameConfigurationController::class, 'updateHardwarePart'])->middleware('permission:configurator.edit');
        Route::delete('/door-frame-configurations/{id}/hardware-parts/{partId}', [DoorFrameConfigurationController::class, 'destroyHardwarePart'])->middleware('permission:configurator.edit');
        Route::post('/door-frame-configurations/{id}/reserve', [DoorFrameConfigurationController::class, 'reserveConfiguration'])->middleware('permission:configurator.release');
        Route::post('/door-frame-configurations/{id}/unreserve', [DoorFrameConfigurationController::class, 'unreserveConfiguration'])->middleware('permission:configurator.release');
        Route::post('/door-frame-configurations/{id}/release', [DoorFrameConfigurationController::class, 'release'])->middleware('permission:configurator.release');
        Route::post('/door-frame-configurations/{id}/create-reservation', [DoorFrameConfigurationController::class, 'createReservation'])->middleware('permission:configurator.release');
        Route::get('/door-frame-configurations/{id}/export-pdf', [DoorFrameConfigurationController::class, 'exportPdf'])->middleware('permission:configurator.view');
        Route::get('/door-frame-configurations/{id}/export-csv', [DoorFrameConfigurationController::class, 'exportCsv'])->middleware('permission:configurator.view');

        // Configurator Catalog (frame systems / series / profiles / components / fasteners)
        Route::get('/config/catalog/tree', [ConfiguratorCatalogController::class, 'tree'])->middleware('permission:configurator.view');

        Route::post('/config/frame-systems', [ConfiguratorCatalogController::class, 'storeSystem'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/frame-systems/{id}', [ConfiguratorCatalogController::class, 'updateSystem'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/frame-systems/{id}', [ConfiguratorCatalogController::class, 'destroySystem'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/frame-series', [ConfiguratorCatalogController::class, 'storeSeries'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/frame-series/{id}', [ConfiguratorCatalogController::class, 'updateSeries'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/frame-series/{id}', [ConfiguratorCatalogController::class, 'destroySeries'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/frame-profiles', [ConfiguratorCatalogController::class, 'storeProfile'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/frame-profiles/{id}', [ConfiguratorCatalogController::class, 'updateProfile'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/frame-profiles/{id}', [ConfiguratorCatalogController::class, 'destroyProfile'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/frame-components', [ConfiguratorCatalogController::class, 'storeComponent'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/frame-components/{id}', [ConfiguratorCatalogController::class, 'updateComponent'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/frame-components/{id}', [ConfiguratorCatalogController::class, 'destroyComponent'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/frame-fasteners', [ConfiguratorCatalogController::class, 'storeFastener'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/frame-fasteners/{id}', [ConfiguratorCatalogController::class, 'updateFastener'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/frame-fasteners/{id}', [ConfiguratorCatalogController::class, 'destroyFastener'])->middleware('permission:configurator.catalog.manage');

        // Configurator Door Catalog (door types / rails / rail lugs / mid lugs / glass specs / setting block kits / tie rods)
        Route::get('/config/door-catalog', [ConfiguratorDoorCatalogController::class, 'index'])->middleware('permission:configurator.view');
        Route::get('/config/products/search-by-part-number', [ConfiguratorDoorCatalogController::class, 'searchProductsByPartNumber'])->middleware('permission:configurator.view');
        Route::get('/config/part-number-usage', [ConfiguratorDoorCatalogController::class, 'partNumberUsage'])->middleware('permission:inventory.view');

        Route::post('/config/door-types', [ConfiguratorDoorCatalogController::class, 'storeDoorType'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/door-types/{id}', [ConfiguratorDoorCatalogController::class, 'updateDoorType'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/door-types/{id}', [ConfiguratorDoorCatalogController::class, 'destroyDoorType'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/rails', [ConfiguratorDoorCatalogController::class, 'storeRail'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/rails/{id}', [ConfiguratorDoorCatalogController::class, 'updateRail'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/rails/{id}', [ConfiguratorDoorCatalogController::class, 'destroyRail'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/rail-lugs', [ConfiguratorDoorCatalogController::class, 'storeRailLug'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/rail-lugs/{id}', [ConfiguratorDoorCatalogController::class, 'updateRailLug'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/rail-lugs/{id}', [ConfiguratorDoorCatalogController::class, 'destroyRailLug'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/mid-lugs', [ConfiguratorDoorCatalogController::class, 'storeMidLug'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/mid-lugs/{id}', [ConfiguratorDoorCatalogController::class, 'updateMidLug'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/mid-lugs/{id}', [ConfiguratorDoorCatalogController::class, 'destroyMidLug'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/glass-specs', [ConfiguratorDoorCatalogController::class, 'storeGlassSpec'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/glass-specs/{id}', [ConfiguratorDoorCatalogController::class, 'updateGlassSpec'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/glass-specs/{id}', [ConfiguratorDoorCatalogController::class, 'destroyGlassSpec'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/setting-block-kits', [ConfiguratorDoorCatalogController::class, 'storeSettingBlockKit'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/setting-block-kits/{id}', [ConfiguratorDoorCatalogController::class, 'updateSettingBlockKit'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/setting-block-kits/{id}', [ConfiguratorDoorCatalogController::class, 'destroySettingBlockKit'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/tie-rods', [ConfiguratorDoorCatalogController::class, 'storeTieRod'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/tie-rods/{id}', [ConfiguratorDoorCatalogController::class, 'updateTieRod'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/tie-rods/{id}', [ConfiguratorDoorCatalogController::class, 'destroyTieRod'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/hinge-spacing-standards', [ConfiguratorDoorCatalogController::class, 'storeHingeSpacingStandard'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hinge-spacing-standards/{id}', [ConfiguratorDoorCatalogController::class, 'updateHingeSpacingStandard'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hinge-spacing-standards/{id}', [ConfiguratorDoorCatalogController::class, 'destroyHingeSpacingStandard'])->middleware('permission:configurator.catalog.manage');

        // Hardware Library Catalog (read-only browse for the hardware step)
        Route::get('/config/hwlib-catalog', [ConfiguratorHwlibCatalogController::class, 'index'])->middleware('permission:configurator.view');

        // Hardware Library Admin (categories/variables/items/backers/fasteners/sets)
        Route::get('/config/hwlib-admin', [ConfiguratorHwlibAdminController::class, 'adminIndex'])->middleware('permission:configurator.view');
        Route::get('/config/settings', [ConfiguratorHwlibAdminController::class, 'settings'])->middleware('permission:configurator.view');
        Route::put('/config/settings', [ConfiguratorHwlibAdminController::class, 'updateSettings'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/hwlib-categories', [ConfiguratorHwlibAdminController::class, 'storeCategory'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-categories/{id}', [ConfiguratorHwlibAdminController::class, 'updateCategory'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-categories/{id}', [ConfiguratorHwlibAdminController::class, 'destroyCategory'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-categories/{id}/variables', [ConfiguratorHwlibAdminController::class, 'setCategoryVariables'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/hwlib-subcategories', [ConfiguratorHwlibAdminController::class, 'storeSubcategory'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-subcategories/{id}', [ConfiguratorHwlibAdminController::class, 'updateSubcategory'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-subcategories/{id}', [ConfiguratorHwlibAdminController::class, 'destroySubcategory'])->middleware('permission:configurator.catalog.manage');

        Route::get('/config/hwlib-variables', [ConfiguratorHwlibAdminController::class, 'indexVariables'])->middleware('permission:configurator.view');
        Route::post('/config/hwlib-variables', [ConfiguratorHwlibAdminController::class, 'storeVariable'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-variables/{id}', [ConfiguratorHwlibAdminController::class, 'updateVariable'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-variables/{id}', [ConfiguratorHwlibAdminController::class, 'destroyVariable'])->middleware('permission:configurator.catalog.manage');

        Route::get('/config/hwlib-functions', [ConfiguratorHwlibAdminController::class, 'indexFunctions'])->middleware('permission:configurator.view');
        Route::post('/config/hwlib-functions', [ConfiguratorHwlibAdminController::class, 'storeFunction'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-functions/{id}', [ConfiguratorHwlibAdminController::class, 'updateFunction'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-functions/{id}', [ConfiguratorHwlibAdminController::class, 'destroyFunction'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/hwlib-items', [ConfiguratorHwlibAdminController::class, 'storeItem'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-items/{id}', [ConfiguratorHwlibAdminController::class, 'updateItem'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-items/{id}', [ConfiguratorHwlibAdminController::class, 'destroyItem'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-items/{id}/values', [ConfiguratorHwlibAdminController::class, 'setItemValues'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-items/{id}/functions', [ConfiguratorHwlibAdminController::class, 'setItemFunctions'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-items/{id}/backers', [ConfiguratorHwlibAdminController::class, 'setItemBackers'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/hwlib-backers', [ConfiguratorHwlibAdminController::class, 'storeBacker'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-backers/{id}', [ConfiguratorHwlibAdminController::class, 'updateBacker'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-backers/{id}', [ConfiguratorHwlibAdminController::class, 'destroyBacker'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-backers/{id}/fasteners', [ConfiguratorHwlibAdminController::class, 'setBackerFasteners'])->middleware('permission:configurator.catalog.manage');

        Route::post('/config/hwlib-fasteners', [ConfiguratorHwlibAdminController::class, 'storeFastener'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-fasteners/{id}', [ConfiguratorHwlibAdminController::class, 'updateFastener'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-fasteners/{id}', [ConfiguratorHwlibAdminController::class, 'destroyFastener'])->middleware('permission:configurator.catalog.manage');

        Route::get('/config/hwlib-sets', [ConfiguratorHwlibAdminController::class, 'indexSets'])->middleware('permission:configurator.view');
        Route::post('/config/hwlib-sets', [ConfiguratorHwlibAdminController::class, 'storeSet'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-sets/{id}', [ConfiguratorHwlibAdminController::class, 'updateSet'])->middleware('permission:configurator.catalog.manage');
        Route::delete('/config/hwlib-sets/{id}', [ConfiguratorHwlibAdminController::class, 'destroySet'])->middleware('permission:configurator.catalog.manage');
        Route::put('/config/hwlib-sets/{id}/items', [ConfiguratorHwlibAdminController::class, 'setSetItems'])->middleware('permission:configurator.catalog.manage');
        Route::post('/config/hwlib-sets/{id}/apply', [ConfiguratorHwlibAdminController::class, 'applySet'])->middleware('permission:configurator.edit');
        Route::delete('/config/hwlib-sets/{id}/apply/{configurationId}', [ConfiguratorHwlibAdminController::class, 'unapplySet'])->middleware('permission:configurator.edit');

        // Fabrication Work Orders and everything scoped under them (drawings,
        // elevations, stages, steps, fab-user list, elevation-type config).
        // Read requires fabrication.work-orders.view; mutations layer on
        // .create / .edit / .delete.
        Route::middleware('permission:fabrication.work-orders.view')->group(function () {
            Route::get('/work-orders', [\App\Http\Controllers\Api\WorkOrderController::class, 'index']);
            Route::get('/work-queue', [\App\Http\Controllers\Api\WorkQueueController::class, 'index']);
            // Static paths BEFORE the /work-orders/{id} wildcard.
            Route::post('/work-orders/resequence-priority', [\App\Http\Controllers\Api\WorkOrderController::class, 'resequencePriority'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/work-orders/reorder', [\App\Http\Controllers\Api\WorkOrderController::class, 'reorder'])->middleware('permission:fabrication.work-orders.edit');
            Route::get('/work-orders/{id}', [\App\Http\Controllers\Api\WorkOrderController::class, 'show']);
            Route::post('/work-orders/parse-excel', [\App\Http\Controllers\Api\WorkOrderController::class, 'parseExcel'])->middleware('permission:fabrication.work-orders.create');
            Route::post('/work-orders', [\App\Http\Controllers\Api\WorkOrderController::class, 'store'])->middleware('permission:fabrication.work-orders.create');
            Route::put('/work-orders/{id}', [\App\Http\Controllers\Api\WorkOrderController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/work-orders/{id}', [\App\Http\Controllers\Api\WorkOrderController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/work-orders/{id}', [\App\Http\Controllers\Api\WorkOrderController::class, 'destroy'])->middleware('permission:fabrication.work-orders.delete');
            Route::put('/work-orders/{id}/assignments', [\App\Http\Controllers\Api\WorkOrderController::class, 'updateAssignments'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/work-orders/{id}/status', [\App\Http\Controllers\Api\WorkOrderController::class, 'updateStatus'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/work-orders/{id}/completion-email', [\App\Http\Controllers\Api\WorkOrderController::class, 'sendCompletionEmail'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/work-orders/{id}/cutlist-upload', [\App\Http\Controllers\Api\WorkOrderController::class, 'uploadCutlist'])->middleware('permission:fabrication.work-orders.edit');

            // Work Order Drawings (shop drawings file uploads)
            Route::get('/work-orders/{id}/drawings', [\App\Http\Controllers\Api\WoDrawingController::class, 'index']);
            Route::get('/work-orders/{id}/drawings/{drawing}/download', [\App\Http\Controllers\Api\WoDrawingController::class, 'download']);
            Route::post('/work-orders/{id}/drawings', [\App\Http\Controllers\Api\WoDrawingController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/work-orders/{id}/drawings/{drawing}', [\App\Http\Controllers\Api\WoDrawingController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');

            // Elevations (per work order)
            Route::get('/work-orders/{id}/elevations', [\App\Http\Controllers\Api\ElevationController::class, 'index']);
            Route::post('/work-orders/{id}/elevations', [\App\Http\Controllers\Api\ElevationController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/elevations/{id}', [\App\Http\Controllers\Api\ElevationController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/elevations/{id}/complete-all-stages', [\App\Http\Controllers\Api\ElevationController::class, 'completeAllStages'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/elevations/{id}', [\App\Http\Controllers\Api\ElevationController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');

            // Configurator openings available to pull into this work order's Door Schedule
            Route::get('/work-orders/{id}/available-configurations', [\App\Http\Controllers\Api\ElevationController::class, 'availableConfigurations']);
            Route::post('/work-orders/{id}/attach-configuration/{configId}', [\App\Http\Controllers\Api\ElevationController::class, 'attachConfiguration'])->middleware('permission:fabrication.work-orders.edit');

            // Elevation Stage cycling (reuse existing stage controller)
            Route::get('/work-order-stages', [\App\Http\Controllers\Api\WorkOrderStageController::class, 'index']);
            Route::patch('/work-orders/{id}/stages/bulk-complete', [\App\Http\Controllers\Api\WorkOrderStageController::class, 'bulkComplete'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/work-order-stages/bulk-assign', [\App\Http\Controllers\Api\WorkOrderStageController::class, 'bulkAssign'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/work-order-stages', [\App\Http\Controllers\Api\WorkOrderStageController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/work-order-stages/{id}', [\App\Http\Controllers\Api\WorkOrderStageController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/work-order-stages/{id}', [\App\Http\Controllers\Api\WorkOrderStageController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');

            // Elevation Types (admin-managed list)
            Route::get('/elevation-types', [\App\Http\Controllers\Api\ElevationTypeController::class, 'index']);
            Route::post('/elevation-types', [\App\Http\Controllers\Api\ElevationTypeController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::put('/elevation-types/{id}', [\App\Http\Controllers\Api\ElevationTypeController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/elevation-types/{id}', [\App\Http\Controllers\Api\ElevationTypeController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/stage-templates', [\App\Http\Controllers\Api\ElevationTypeController::class, 'storeTemplate'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/stage-templates/{id}', [\App\Http\Controllers\Api\ElevationTypeController::class, 'updateTemplate'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/stage-templates/{id}', [\App\Http\Controllers\Api\ElevationTypeController::class, 'destroyTemplate'])->middleware('permission:fabrication.work-orders.edit');

            // Complexity tiers (stage template sets)
            Route::get('/stage-template-sets', [\App\Http\Controllers\Api\StageTemplateSetController::class, 'index']);
            Route::post('/stage-template-sets', [\App\Http\Controllers\Api\StageTemplateSetController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/stage-template-sets/{id}', [\App\Http\Controllers\Api\StageTemplateSetController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/stage-template-sets/{id}', [\App\Http\Controllers\Api\StageTemplateSetController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');

            Route::get('/fab-users', [\App\Http\Controllers\Api\FabUserController::class, 'index']);
            Route::post('/fab-users', [\App\Http\Controllers\Api\FabUserController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::put('/fab-users/{id}', [\App\Http\Controllers\Api\FabUserController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/fab-users/{id}', [\App\Http\Controllers\Api\FabUserController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/fab-users/{id}/set-pin', [\App\Http\Controllers\Api\FabUserController::class, 'setPin'])->middleware('permission:fabrication.work-orders.edit');

            Route::get('/work-orders/{workOrderId}/steps', [\App\Http\Controllers\Api\JobStepController::class, 'index']);
            Route::patch('/work-orders/{workOrderId}/steps/complete-all', [\App\Http\Controllers\Api\JobStepController::class, 'completeAll'])->middleware('permission:fabrication.work-orders.edit');
            Route::post('/job-steps', [\App\Http\Controllers\Api\JobStepController::class, 'store'])->middleware('permission:fabrication.work-orders.edit');
            Route::patch('/job-steps/{id}', [\App\Http\Controllers\Api\JobStepController::class, 'update'])->middleware('permission:fabrication.work-orders.edit');
            Route::delete('/job-steps/{id}', [\App\Http\Controllers\Api\JobStepController::class, 'destroy'])->middleware('permission:fabrication.work-orders.edit');
        });

        // Fabrication Documents
        Route::get('/fabrication-documents/filter-options', [FabricationDocumentController::class, 'filterOptions'])->middleware('permission:fabrication.view');
        Route::get('/fabrication-documents', [FabricationDocumentController::class, 'index'])->middleware('permission:fabrication.view');
        Route::post('/fabrication-documents', [FabricationDocumentController::class, 'store'])->middleware('permission:fabrication.create');
        Route::get('/fabrication-documents/{fabricationDocument}', [FabricationDocumentController::class, 'show'])->middleware('permission:fabrication.view');
        // Laravel's method-parameter override (Request::capture()) treats a POST with
        // a `_method=PUT` body field as PUT before routing, so this single PUT route
        // already handles the frontend's multipart POST-with-_method=PUT edit calls.
        Route::put('/fabrication-documents/{fabricationDocument}', [FabricationDocumentController::class, 'update'])->middleware('permission:fabrication.edit');
        Route::delete('/fabrication-documents/{fabricationDocument}', [FabricationDocumentController::class, 'destroy'])->middleware('permission:fabrication.delete');

        // Quality Reports (PDF ingestion, elevation matching, verification)
        Route::middleware('permission:quality.view')->group(function () {
            Route::get('/quality-reports', [\App\Http\Controllers\Api\QualityReportController::class, 'index']);
            // Static path BEFORE the /quality-reports/{id} wildcard.
            Route::get('/quality-reports/elevation-options', [\App\Http\Controllers\Api\QualityReportController::class, 'elevationOptions']);
            Route::get('/quality-reports/analytics/incident-rate', [\App\Http\Controllers\Api\QualityAnalyticsController::class, 'incidentRateByMonth']);
            Route::get('/quality-reports/analytics/problem-types', [\App\Http\Controllers\Api\QualityAnalyticsController::class, 'problemTypeRolling13Week']);
            Route::get('/quality-reports/analytics/weekly-trend', [\App\Http\Controllers\Api\QualityAnalyticsController::class, 'weeklyTrend13Week']);
            Route::post('/quality-reports/analytics/export-pdf', [\App\Http\Controllers\Api\QualityAnalyticsController::class, 'exportPdf']);
            Route::get('/quality-reports/export/csv', [\App\Http\Controllers\Api\QualityReportController::class, 'exportCsv']);
            Route::get('/quality-reports/export/pdf', [\App\Http\Controllers\Api\QualityReportController::class, 'exportPdf']);
            Route::get('/quality-reports/{id}', [\App\Http\Controllers\Api\QualityReportController::class, 'show']);
            Route::get('/quality-reports/{id}/files/{fileId}/download', [\App\Http\Controllers\Api\QualityReportController::class, 'downloadFile']);
            Route::get('/quality-reports/{id}/files/{fileId}/view', [\App\Http\Controllers\Api\QualityReportController::class, 'viewFile']);
            Route::post('/quality-reports', [\App\Http\Controllers\Api\QualityReportController::class, 'store'])->middleware('permission:quality.create');
            Route::put('/quality-reports/{id}', [\App\Http\Controllers\Api\QualityReportController::class, 'update'])->middleware('permission:quality.edit');
            Route::post('/quality-reports/{id}/rematch', [\App\Http\Controllers\Api\QualityReportController::class, 'rematch'])->middleware('permission:quality.edit');
            Route::post('/quality-reports/{id}/verify', [\App\Http\Controllers\Api\QualityReportController::class, 'verify'])->middleware('permission:quality.verify');
            Route::post('/quality-reports/{id}/review', [\App\Http\Controllers\Api\QualityReportController::class, 'review'])->middleware('permission:quality.verify');
            Route::post('/quality-reports/{id}/reject', [\App\Http\Controllers\Api\QualityReportController::class, 'reject'])->middleware('permission:quality.verify');
            Route::delete('/quality-reports/{id}', [\App\Http\Controllers\Api\QualityReportController::class, 'destroy'])->middleware('permission:quality.delete');
        });
    });
});
