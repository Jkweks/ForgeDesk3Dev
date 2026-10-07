<?php

/*
 * Primary navigation, rendered twice by partials/nav-menu.blade.php: once in the
 * top navbar and once in the sidebar (Tabler shows one based on the user's
 * `navbar-position` preference).
 *
 * Section keys
 *   label       Visible title.
 *   icon        Tabler icon name (webfont class `ti ti-<icon>`).
 *   permission  `nav.*` permission (or a list: visible if the user has ANY of them); becomes
 *               data-nav-permission so applyNavigationPermissions() can hide it.
 *   href        Leaf section (no dropdown).
 *   active      Request::is() patterns that mark the section active.
 *   items       Dropdown entries (below).
 *
 * Item keys
 *   divider     true => a separator, nothing else.
 *   header      a group heading ('Catalog', 'Stock', ...), nothing else. Hidden by
 *               applyNavigationPermissions() when none of its group's items is visible.
 *   nav         The `nav.*` permission an item needs. Required on items of a section whose
 *               `permission` is a list, so moving an item between sections never changes who
 *               sees it (it keeps the permission of the section it came from).
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
        // Stock and purchasing. Purchasing + stock control used to live under "Operations" (nav.operations).
        'label' => 'Inventory', 'icon' => 'package', 'permission' => ['nav.inventory', 'nav.operations'],
        'active' => ['inventory/*', 'low-stock', 'critical-stock', 'transactions', 'categories', 'suppliers',
            'purchase-orders', 'cycle-counting', 'storage-locations', 'operations/*', 'admin/location-assignment'],
        'items' => [
            ['header' => 'Catalog'],
            ['label' => 'All Products', 'href' => '/inventory/products', 'active' => ['inventory/products'], 'nav' => 'nav.inventory'],
            ['label' => 'Categories', 'href' => '/categories', 'nav' => 'nav.inventory'],
            ['label' => 'Suppliers', 'href' => '/suppliers', 'nav' => 'nav.inventory'],
            ['header' => 'Stock'],
            ['label' => 'Low Stock', 'href' => '/low-stock', 'nav' => 'nav.inventory'],
            ['label' => 'Critical Stock', 'href' => '/critical-stock', 'nav' => 'nav.inventory'],
            ['label' => 'Cycle Counting', 'href' => '/cycle-counting', 'nav' => 'nav.operations'],
            ['label' => 'Storage Locations', 'href' => '/storage-locations', 'nav' => 'nav.operations'],
            ['label' => 'Assign Locations', 'href' => '/admin/location-assignment', 'permission' => 'inventory.edit', 'nav' => 'nav.operations'],
            ['header' => 'Activity'],
            ['label' => 'Transaction History', 'href' => '/transactions', 'nav' => 'nav.inventory'],
            ['header' => 'Purchasing'],
            ['label' => 'Replenishment', 'href' => '/operations/replenishment', 'active' => ['operations/replenishment'], 'nav' => 'nav.operations'],
            ['label' => 'Purchase Orders', 'href' => '/purchase-orders', 'nav' => 'nav.operations'],
        ],
    ],
    [
        'label' => 'Jobs', 'icon' => 'truck-delivery', 'permission' => 'nav.fulfillment',
        'active' => ['jobs', 'fulfillment*'],
        'items' => [
            ['label' => 'Jobs Dashboard', 'href' => '/jobs', 'active' => ['jobs']],
            ['divider' => true],
            ['label' => 'Material Check', 'href' => '/fulfillment/material-check'],
            ['label' => 'Reservations Dashboard', 'href' => '/fulfillment/job-reservations', 'permission' => 'reservations.dashboard.view'],
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
            ['label' => 'Documents', 'href' => '/fabrication/documents', 'permission' => 'fabrication.view', 'active' => ['fabrication/documents']],
            ['header' => 'Displays'],
            ['label' => 'Shop Floor Display', 'href' => '/shop', 'target' => '_blank', 'external' => true],
            ['label' => 'Cut Station', 'href' => '/cut-station', 'target' => '_blank', 'external' => true, 'admin_only' => true],
        ],
    ],
    [
        // The external Door Configurator used to sit under Fulfillment (nav.fulfillment), so it keeps that permission.
        'label' => 'Configurator', 'icon' => 'adjustments-horizontal', 'permission' => ['nav.configurator', 'nav.fulfillment'],
        'active' => ['config*'],
        'items' => [
            ['label' => 'Frame Builder', 'href' => '/config', 'permission' => 'configurator.view', 'active' => ['config'], 'nav' => 'nav.configurator'],
            ['label' => 'Fabrication Package', 'href' => '/config/package', 'permission' => 'configurator.view', 'active' => ['config/package'], 'nav' => 'nav.configurator'],
            ['label' => 'Door Labels', 'href' => '/config/labels', 'permission' => 'configurator.view', 'active' => ['config/labels'], 'nav' => 'nav.configurator'],
            ['divider' => true],
            ['label' => 'Configurator Admin', 'href' => '/config/admin', 'permission' => 'configurator.catalog.manage', 'active' => ['config/admin'], 'nav' => 'nav.configurator'],
            ['divider' => true],
            ['label' => 'Door Configurator', 'href' => 'http://fab.vosglassintra.net/configurator', 'target' => '_blank', 'external' => true, 'badge' => ['Beta', 'bg-yellow-lt text-yellow'], 'nav' => 'nav.fulfillment'],
        ],
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
        'label' => 'Reports', 'icon' => 'chart-bar', 'permission' => 'nav.reports',
        'href' => '/reports', 'active' => ['reports'],
    ],
    [
        'label' => 'Admin', 'icon' => 'settings', 'permission' => 'nav.admin',
        'href' => '/admin', 'active' => ['admin'], 'current' => ['admin'],
    ],
];
