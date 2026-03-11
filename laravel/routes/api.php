<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\MaintenanceTaskController;
use App\Http\Controllers\Api\MaintenanceRecordController;
use App\Http\Controllers\Api\InventoryLocationController;
use App\Http\Controllers\Api\JobReservationController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\RequiredPartsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\CycleCountController;
use App\Http\Controllers\Api\MachineToolingController;
use App\Http\Controllers\Api\MaterialCheckController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\BusinessJobController;
use App\Http\Controllers\Api\DoorFrameConfigurationController;
use App\Http\Controllers\Api\PasswordResetController;

// Public test route (no auth required)
Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::get('/v1/test', function () {
    return response()->json([
        'message' => 'ForgeDesk API is working!',
        'version' => '1.0'
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

    if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Check if user is active
    if (!$user->is_active) {
        return response()->json(['message' => 'Your account has been deactivated. Please contact an administrator.'], 403);
    }

    // Update last login timestamp
    $user->updateLastLogin();

    // Token expiration based on remember me
    $remember = $request->boolean('remember', false);
    $expirationMinutes = $remember ? 43200 : config('sanctum.expiration', 480); // 30 days or 8 hours
    $expiresAt = now()->addMinutes($expirationMinutes);

    // Create token with expiration
    $tokenResult = $user->createToken('auth-token', ['*'], $expiresAt);
    $token = $tokenResult->plainTextToken;

    // Get user permissions
    $permissions = [];
    if ($user->role) {
        $role = \App\Models\Role::where('name', $user->role)->first();
        if ($role) {
            $permissions = $role->permissions->pluck('name')->toArray();
        }
    }

    return response()->json([
        'token' => $token,
        'expires_at' => $expiresAt->toIso8601String(),
        'expires_in' => $expirationMinutes * 60, // in seconds
        'remember' => $remember,
        'user' => [
            'id' => $user->id,
            'name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'permissions' => $permissions,
        ]
    ]);
});

Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out']);
})->middleware('auth:sanctum');

// Token Refresh (authenticated)
Route::post('/token/refresh', function (Request $request) {
    $user = $request->user();
    $currentToken = $request->user()->currentAccessToken();

    // Get current token's abilities to check for remember me
    $abilities = $currentToken->abilities ?? ['*'];
    $tokenName = $currentToken->name;

    // Detect "remember me" by comparing the token's original lifespan (created_at → expires_at).
    // Checking remaining time would always fail near expiry, so we use the original duration instead.
    $currentExpiration = $currentToken->expires_at;
    $isRememberToken = false;

    if ($currentExpiration) {
        $originalDurationMinutes = $currentToken->created_at->diffInMinutes($currentExpiration);
        // Original duration > 24 hours means this was a "remember me" token
        $isRememberToken = $originalDurationMinutes > 1440;
    }

    // Also accept an explicit hint from the client (fallback for tokens created before this fix)
    if (!$isRememberToken && $request->has('remember')) {
        $isRememberToken = $request->boolean('remember', false);
    }

    // Set expiration based on token type
    $expirationMinutes = $isRememberToken ? 43200 : config('sanctum.expiration', 480);
    $expiresAt = now()->addMinutes($expirationMinutes);

    // Delete old token
    $currentToken->delete();

    // Create new token
    $tokenResult = $user->createToken($tokenName, $abilities, $expiresAt);
    $token = $tokenResult->plainTextToken;

    // Get user permissions
    $permissions = [];
    if ($user->role) {
        $role = \App\Models\Role::where('name', $user->role)->first();
        if ($role) {
            $permissions = $role->permissions->pluck('name')->toArray();
        }
    }

    return response()->json([
        'token' => $token,
        'expires_at' => $expiresAt->toIso8601String(),
        'expires_in' => $expirationMinutes * 60, // in seconds
        'remember' => $isRememberToken,
        'user' => [
            'id' => $user->id,
            'name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'permissions' => $permissions,
        ]
    ]);
})->middleware('auth:sanctum');

// Password Reset routes (public)
Route::post('/password/forgot', [PasswordResetController::class, 'forgotPassword']);
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
Route::post('/password/verify-token', [PasswordResetController::class, 'verifyToken']);

// Fulfillment routes (public for internal use)
Route::prefix('v1')->group(function () {
    Route::get('/fulfillment/test', [MaterialCheckController::class, 'test']);
    Route::post('/fulfillment/material-check', [MaterialCheckController::class, 'checkMaterials']);
    Route::post('/fulfillment/commit-materials', [MaterialCheckController::class, 'commitMaterials']);

    // Job Reservations
    // IMPORTANT: Specific routes MUST come before parameterized routes like {id}
    Route::get('/job-reservations', [JobReservationController::class, 'index']);
    Route::post('/job-reservations/create-manual', [JobReservationController::class, 'createManual']);
    Route::get('/job-reservations/search-product', [JobReservationController::class, 'searchProduct']);
    Route::get('/job-reservations/search-products', [JobReservationController::class, 'searchProducts']);
    Route::get('/job-reservations/status-labels', [JobReservationController::class, 'statusLabels']);
    Route::get('/job-reservations/{id}', [JobReservationController::class, 'show']);
    Route::put('/job-reservations/{id}', [JobReservationController::class, 'updateReservation']);
    Route::post('/job-reservations/{id}/status', [JobReservationController::class, 'updateStatus']);
    Route::post('/job-reservations/{id}/complete', [JobReservationController::class, 'complete']);
    Route::post('/job-reservations/{id}/items', [JobReservationController::class, 'addItem']);
    Route::put('/job-reservations/{id}/items/{itemId}', [JobReservationController::class, 'updateItem']);
    Route::post('/job-reservations/{id}/items/{itemId}/replace', [JobReservationController::class, 'replaceItem']);
    Route::delete('/job-reservations/{id}/items/{itemId}', [JobReservationController::class, 'removeItem']);

    // EZ Estimate Management (called from admin web interface)
    Route::get('/ez-estimate/test', [\App\Http\Controllers\Api\EzEstimateController::class, 'test']);
    Route::get('/ez-estimate/debug', [\App\Http\Controllers\Api\EzEstimateController::class, 'debug']);
    Route::get('/ez-estimate/test-pricing', [\App\Http\Controllers\Api\EzEstimateController::class, 'testPricing']);
    Route::post('/ez-estimate/upload', [\App\Http\Controllers\Api\EzEstimateController::class, 'upload']);
    Route::get('/ez-estimate/current-file', [\App\Http\Controllers\Api\EzEstimateController::class, 'getCurrentFile']);
    Route::get('/ez-estimate/stats', [\App\Http\Controllers\Api\EzEstimateController::class, 'getStats']);

    // Test endpoint for inventory status calculations (public for testing)
    Route::get('/products/test/status-calculations', [ProductController::class, 'testStatusCalculations']);
    Route::post('/products/recalculate-statuses', [ProductController::class, 'recalculateStatuses']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1')->group(function () {
        // Current user
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // User Management
        Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index']);
        Route::get('/users/statistics', [\App\Http\Controllers\Api\UserController::class, 'statistics']);
        Route::get('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'show']);
        Route::post('/users', [\App\Http\Controllers\Api\UserController::class, 'store']);
        Route::put('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'update']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'destroy']);
        Route::post('/users/{user}/restore', [\App\Http\Controllers\Api\UserController::class, 'restore']);
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Api\UserController::class, 'resetPassword']);

        // Self-service user endpoints
        Route::post('/user/change-password', [\App\Http\Controllers\Api\UserController::class, 'changePassword']);
        Route::put('/user/profile', [\App\Http\Controllers\Api\UserController::class, 'updateProfile']);

        // Role & Permission Management
        Route::get('/roles', [\App\Http\Controllers\Api\RoleController::class, 'index']);
        Route::get('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'show']);
        Route::post('/roles', [\App\Http\Controllers\Api\RoleController::class, 'store']);
        Route::put('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'update']);
        Route::delete('/roles/{role}', [\App\Http\Controllers\Api\RoleController::class, 'destroy']);
        Route::get('/permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions']);
        Route::post('/roles/{role}/permissions', [\App\Http\Controllers\Api\RoleController::class, 'assignPermissions']);

        // System Status
        Route::get('/status', [StatusController::class, 'index']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/inventory/{status}', [DashboardController::class, 'inventoryByStatus']);
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        
        // Categories
        Route::apiResource('categories', CategoryController::class);
        Route::get('/categories-tree', [CategoryController::class, 'tree']);
        Route::get('/category-tree', [CategoryController::class, 'tree']); // Alias for frontend compatibility
        Route::get('/category-systems', [CategoryController::class, 'systems']);
        Route::post('/categories/sort-order', [CategoryController::class, 'updateSortOrder']);
        Route::post('/categories/bulk-action', [CategoryController::class, 'bulkAction']);

        // Suppliers
        Route::apiResource('suppliers', SupplierController::class);
        Route::get('/supplier-countries', [SupplierController::class, 'countries']);
        Route::get('/supplier-statistics', [SupplierController::class, 'statistics']);
        Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products']);
        Route::get('/suppliers/{supplier}/contacts', [SupplierController::class, 'contacts']);
        Route::get('/suppliers/{supplier}/low-stock-report', [SupplierController::class, 'lowStockReport']);
        Route::post('/suppliers/bulk-action', [SupplierController::class, 'bulkAction']);

        // Products
        Route::get('/products-search', [ProductController::class, 'search']); // Non-conflicting route
        Route::apiResource('products', ProductController::class);
        Route::post('/products/{product}/adjust', [ProductController::class, 'adjustInventory']);
        Route::post('/products/{product}/issue-to-job', [ProductController::class, 'issueToJob']);
        Route::get('/products/{product}/transactions', [ProductController::class, 'getTransactions']);
        Route::get('/products/{product}/calculate-reorder', [ProductController::class, 'calculateReorderPoint']);
        Route::get('/finish-codes', [ProductController::class, 'getFinishCodes']);
        Route::get('/unit-of-measures', [ProductController::class, 'getUnitOfMeasures']);

        // Inventory Locations
        Route::get('/products/{product}/locations', [InventoryLocationController::class, 'index']);
        Route::post('/products/{product}/locations', [InventoryLocationController::class, 'store']);
        Route::put('/products/{product}/locations/{location}', [InventoryLocationController::class, 'update']);
        Route::delete('/products/{product}/locations/{location}', [InventoryLocationController::class, 'destroy']);
        Route::post('/products/{product}/locations/transfer', [InventoryLocationController::class, 'transfer']);
        Route::post('/products/{product}/locations/{location}/adjust', [InventoryLocationController::class, 'adjust']);
        Route::get('/products/{product}/locations/statistics', [InventoryLocationController::class, 'statistics']);
        Route::get('/locations', [InventoryLocationController::class, 'getAllLocations']);

        // Storage Locations (Master Location Management)
        Route::get('/storage-locations-tree', [App\Http\Controllers\Api\StorageLocationController::class, 'tree']);
        Route::get('/storage-locations-stats', [App\Http\Controllers\Api\StorageLocationController::class, 'withStats']);
        Route::get('/storage-locations-names', [App\Http\Controllers\Api\StorageLocationController::class, 'locationNames']);
        Route::apiResource('storage-locations', App\Http\Controllers\Api\StorageLocationController::class);

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
        Route::post('/transactions/manual', [InventoryTransactionController::class, 'createManual']);
        Route::get('/transactions/{transaction}', [InventoryTransactionController::class, 'show']);
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

        // Reports & Analytics
        Route::get('/reports/low-stock', [ReportsController::class, 'lowStockReport']);
        Route::get('/reports/committed-parts', [ReportsController::class, 'committedPartsReport']);
        Route::get('/reports/velocity', [ReportsController::class, 'stockVelocityAnalysis']);
        Route::get('/reports/reorder-recommendations', [ReportsController::class, 'reorderRecommendations']);
        Route::get('/reports/obsolete', [ReportsController::class, 'obsoleteInventory']);
        Route::get('/reports/usage-analytics', [ReportsController::class, 'usageAnalytics']);
        Route::get('/reports/monthly-statement', [ReportsController::class, 'monthlyInventoryStatement']);
        Route::get('/reports/export', [ReportsController::class, 'exportReport']);

        // PDF Reports
        Route::get('/reports/low-stock/pdf', [ReportsController::class, 'lowStockPdf']);
        Route::get('/reports/committed-parts/pdf', [ReportsController::class, 'committedPartsPdf']);
        Route::get('/reports/velocity/pdf', [ReportsController::class, 'velocityAnalysisPdf']);
        Route::get('/reports/reorder-recommendations/pdf', [ReportsController::class, 'reorderRecommendationsPdf']);
        Route::get('/reports/obsolete/pdf', [ReportsController::class, 'obsoleteInventoryPdf']);
        Route::get('/reports/usage-analytics/pdf', [ReportsController::class, 'usageAnalyticsPdf']);
        Route::get('/reports/monthly-statement/pdf', [ReportsController::class, 'monthlyInventoryStatementPdf']);
        Route::get('/reports/inventory/data', [ReportsController::class, 'inventoryReportData']);
        Route::get('/reports/inventory/csv', [ReportsController::class, 'exportInventoryCsv']);
        Route::get('/reports/inventory/pdf', [ReportsController::class, 'inventoryReportPdf']);

        // Purchase Orders
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::get('/purchase-orders-open', [PurchaseOrderController::class, 'open']);
        Route::get('/purchase-orders-statistics', [PurchaseOrderController::class, 'statistics']);

        // Cycle Counting
        Route::apiResource('cycle-counts', CycleCountController::class);
        Route::post('/cycle-counts/{cycleCountSession}/start', [CycleCountController::class, 'start']);
        Route::post('/cycle-counts/{cycleCountSession}/record-count', [CycleCountController::class, 'recordCount']);
        Route::post('/cycle-counts/{cycleCountSession}/approve-variances', [CycleCountController::class, 'approveVariances']);
        Route::post('/cycle-counts/{cycleCountSession}/complete', [CycleCountController::class, 'complete']);
        Route::post('/cycle-counts/{cycleCountSession}/cancel', [CycleCountController::class, 'cancel']);
        Route::get('/cycle-counts/{cycleCountSession}/variance-report', [CycleCountController::class, 'varianceReport']);
        Route::get('/cycle-counts/{cycleCountSession}/pdf', [CycleCountController::class, 'generatePdf']);
        Route::get('/cycle-counts-active', [CycleCountController::class, 'active']);
        Route::get('/cycle-counts-statistics', [CycleCountController::class, 'statistics']);

        // Orders
        Route::apiResource('orders', OrderController::class);
        Route::post('/orders/{order}/commit', [OrderController::class, 'commitInventory']);
        Route::post('/orders/{order}/release', [OrderController::class, 'releaseInventory']);
        Route::post('/orders/{order}/ship', [OrderController::class, 'shipOrder']);
        
        // Import/Export
        Route::post('/import/products', [ImportExportController::class, 'importProducts']);
        Route::get('/export/products', [ImportExportController::class, 'exportProducts']);
        Route::get('/export/template', [ImportExportController::class, 'downloadTemplate']);

        // Maintenance
        Route::get('/maintenance/dashboard', [MaintenanceController::class, 'dashboard']);
        Route::get('/maintenance/upcoming-tasks', [MaintenanceController::class, 'upcomingTasks']);
        Route::get('/maintenance/recent-records', [MaintenanceController::class, 'recentRecords']);

        // Machines
        Route::apiResource('machines', MachineController::class);
        Route::get('/machine-types', [MachineController::class, 'getTypes']);

        // Assets
        Route::apiResource('assets', AssetController::class);

        // Maintenance Tasks
        Route::apiResource('maintenance-tasks', MaintenanceTaskController::class);

        // Maintenance Records
        Route::apiResource('maintenance-records', MaintenanceRecordController::class);

        // Machine Tooling
        Route::get('/machine-tooling/inventory', [MachineToolingController::class, 'inventory']);
        Route::get('/machine-tooling/all', [MachineToolingController::class, 'all']);
        Route::get('/machine-tooling/statistics', [MachineToolingController::class, 'statistics']);
        Route::get('/machine-tooling/tool-life-units', [MachineToolingController::class, 'toolLifeUnits']);
        Route::get('/machine-tooling/tool-types', [MachineToolingController::class, 'toolTypes']);
        Route::get('/machines/{machine}/tooling', [MachineToolingController::class, 'index']);
        Route::post('/machines/{machine}/tooling', [MachineToolingController::class, 'store']);
        Route::get('/machines/{machine}/tooling/compatible-tools', [MachineToolingController::class, 'compatibleTools']);
        Route::get('/machine-tooling/{id}', [MachineToolingController::class, 'show']);
        Route::put('/machine-tooling/{id}/update-life', [MachineToolingController::class, 'updateToolLife']);
        Route::post('/machine-tooling/{id}/replace', [MachineToolingController::class, 'replace']);
        Route::post('/machine-tooling/{id}/remove', [MachineToolingController::class, 'remove']);

        // Business Jobs (Project Management)
        Route::apiResource('business-jobs', BusinessJobController::class);

        // Job-specific Reservations
        Route::get('/business-jobs/{jobId}/reservations', [BusinessJobController::class, 'getReservations']);
        Route::post('/business-jobs/{jobId}/reservations', [BusinessJobController::class, 'createReservation']);
        Route::get('/business-jobs/{jobId}/reservations/{reservationId}', [BusinessJobController::class, 'getReservation']);
        Route::post('/business-jobs/{jobId}/reservations/{reservationId}/status', [BusinessJobController::class, 'updateReservationStatus']);
        Route::delete('/business-jobs/{jobId}/reservations/{reservationId}', [BusinessJobController::class, 'deleteReservation']);

        // Door/Frame Configurator
        Route::get('/door-frame-configurations', [DoorFrameConfigurationController::class, 'index']);
        Route::post('/door-frame-configurations', [DoorFrameConfigurationController::class, 'store']);
        Route::get('/door-frame-configurations/{id}', [DoorFrameConfigurationController::class, 'show']);
        Route::put('/door-frame-configurations/{id}/opening-specs', [DoorFrameConfigurationController::class, 'updateOpeningSpecs']);
        Route::put('/door-frame-configurations/{id}/frame-config', [DoorFrameConfigurationController::class, 'updateFrameConfig']);
        Route::put('/door-frame-configurations/{id}/frame-parts', [DoorFrameConfigurationController::class, 'updateFrameParts']);
        Route::put('/door-frame-configurations/{id}/door-config', [DoorFrameConfigurationController::class, 'updateDoorConfig']);
        Route::post('/door-frame-configurations/{id}/release', [DoorFrameConfigurationController::class, 'release']);
    });
});