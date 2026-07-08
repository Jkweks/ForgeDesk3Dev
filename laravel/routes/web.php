<?php

use Illuminate\Support\Facades\Route;

// Login route (required by Laravel auth system)
Route::get('/login', function () {
    return view('dashboard'); // The dashboard view handles login UI
})->name('login');

Route::get('/', function () {
    return view('dashboard');
});

// Inventory Management
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
Route::get('/reports', function () {
    return view('reports');
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

// Dev Issue Tracker
Route::get('/dev-issues', function () {
    return view('dev-issues');
});
