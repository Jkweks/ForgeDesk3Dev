<?php

/*
 * CLASSIC (original) navigation, kept verbatim for users who choose "Original" under
 * Customize -> Menu organization. config/navigation.php is the current structure.
 * Do not reorganize this file; it exists so the old muscle memory keeps working.
 *
 * Primary navigation, rendered twice by partials/nav-menu.blade.php: once in the
 * top navbar and once in the sidebar (Tabler shows one based on the user's
 * `navbar-position` preference).
 *
 * Section keys
 *   label       Visible title.
 *   icon        Tabler icon name (webfont class `ti ti-<icon>`).
 *   permission  `nav.*` permission; becomes data-nav-permission so
 *               applyNavigationPermissions() (partials/auth-scripts) can hide it.
 *   href        Leaf section (no dropdown).
 *   active      Request::is() patterns that mark the section active.
 *   items       Dropdown entries (below).
 *
 * Item keys
 *   divider     true => a separator, nothing else.
 *   label, href
 *   permission  Optional data-permission (hidden by applyActionPermissions()).
 *   active      Request::is() patterns that mark just this item active.
 *   target      e.g. _blank (adds rel=noopener; with `external` an icon is shown).
 *   badge       [text, css classes].
 *   admin_only  Rendered only for admin users.
 */
return [
    [
        'label' => 'Dashboard', 'icon' => 'home', 'permission' => 'nav.dashboard',
        'href' => '/', 'active' => ['/'],
    ],
    [
        'label' => 'Inventory', 'icon' => 'package', 'permission' => 'nav.inventory',
        'active' => ['inventory/*', 'low-stock', 'critical-stock', 'transactions', 'categories', 'suppliers'],
        'items' => [
            ['label' => 'All Products', 'href' => '/inventory/products', 'active' => ['inventory/products']],
            ['label' => 'Low Stock', 'href' => '/low-stock'],
            ['label' => 'Critical Stock', 'href' => '/critical-stock'],
            ['divider' => true],
            ['label' => 'Transaction History', 'href' => '/transactions'],
            ['label' => 'Categories', 'href' => '/categories'],
            ['label' => 'Suppliers', 'href' => '/suppliers'],
        ],
    ],
    [
        'label' => 'Operations', 'icon' => 'building-factory-2', 'permission' => 'nav.operations',
        'active' => ['purchase-orders', 'cycle-counting', 'operations/*'],
        'items' => [
            ['label' => 'Replenishment', 'href' => '/operations/replenishment', 'active' => ['operations/replenishment']],
            ['label' => 'Purchase Orders', 'href' => '/purchase-orders'],
            ['divider' => true],
            ['label' => 'Cycle Counting', 'href' => '/cycle-counting'],
            ['label' => 'Storage Locations', 'href' => '/storage-locations'],
        ],
    ],
    [
        'label' => 'Fulfillment', 'icon' => 'truck-delivery', 'permission' => 'nav.fulfillment',
        'active' => ['fulfillment*'],
        'items' => [
            ['label' => 'Jobs Dashboard', 'href' => '/jobs'],
            ['divider' => true],
            ['label' => 'Material Check', 'href' => '/fulfillment/material-check'],
            ['label' => 'Reservations Dashboard', 'href' => '/fulfillment/job-reservations', 'permission' => 'reservations.dashboard.view'],
            ['divider' => true],
            ['label' => 'Door Configurator', 'href' => 'http://fab.vosglassintra.net/configurator', 'target' => '_blank', 'badge' => ['Beta', 'bg-yellow-lt text-yellow']],
        ],
    ],
    [
        'label' => 'Configurator', 'icon' => 'adjustments-horizontal', 'permission' => 'nav.configurator',
        'active' => ['config*'],
        'items' => [
            ['label' => 'Frame Builder', 'href' => '/config', 'permission' => 'configurator.view', 'active' => ['config']],
            ['label' => 'Fabrication Package', 'href' => '/config/package', 'permission' => 'configurator.view', 'active' => ['config/package']],
            ['label' => 'Door Labels', 'href' => '/config/labels', 'permission' => 'configurator.view', 'active' => ['config/labels']],
            ['divider' => true],
            ['label' => 'Configurator Admin', 'href' => '/config/admin', 'permission' => 'configurator.catalog.manage', 'active' => ['config/admin']],
        ],
    ],
    [
        'label' => 'Reports', 'icon' => 'chart-bar', 'permission' => 'nav.reports',
        'href' => '/reports', 'active' => ['reports'],
    ],
    [
        'label' => 'Maintenance', 'icon' => 'tool', 'permission' => 'nav.maintenance',
        'active' => ['maintenance*'],
        'items' => [
            ['label' => 'Maintenance Hub', 'href' => '/maintenance'],
            ['label' => 'Machines', 'href' => '/maintenance#tab-machines'],
            ['label' => 'Tasks', 'href' => '/maintenance#tab-tasks'],
            ['label' => 'Service Log', 'href' => '/maintenance#tab-records'],
            ['divider' => true],
            ['label' => 'Assets', 'href' => '/maintenance#tab-assets'],
        ],
    ],
    [
        'label' => 'Fabrication', 'icon' => 'hammer', 'permission' => 'nav.fabrication',
        'active' => ['fabrication*'],
        'items' => [
            ['label' => 'Work Orders', 'href' => '/fabrication/work-orders', 'active' => ['fabrication/work-orders']],
            ['label' => 'Work Queue', 'href' => '/fabrication/work-queue', 'permission' => 'fabrication.work-orders.view', 'active' => ['fabrication/work-queue']],
            ['label' => 'Cut Lists', 'href' => '/fabrication/cut-lists', 'permission' => 'fabrication.work-orders.view', 'active' => ['fabrication/cut-lists']],
            ['label' => 'Quality Reports', 'href' => '/fabrication/quality', 'permission' => 'quality.view', 'active' => ['fabrication/quality']],
            ['label' => 'Shop Floor Display', 'href' => '/shop', 'target' => '_blank', 'external' => true],
            ['label' => 'Cut Station', 'href' => '/cut-station', 'target' => '_blank', 'external' => true, 'admin_only' => true],
        ],
    ],
    [
        'label' => 'Admin', 'icon' => 'settings', 'permission' => 'nav.admin',
        'href' => '/admin', 'active' => ['admin*'], 'current' => ['admin'],
    ],
];
