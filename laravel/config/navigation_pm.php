<?php

/*
 * Project Management workspace navigation (front of house), rendered by
 * partials/nav-menu.blade.php for every /pm/* page. Same format as
 * config/navigation.php. Add entries here only when the page exists.
 */
return [
    [
        'label' => 'Dashboard', 'icon' => 'home', 'permission' => 'nav.pm',
        'href' => '/pm', 'active' => ['pm'],
    ],
    [
        'label' => 'To-do', 'icon' => 'checklist', 'permission' => 'nav.pm',
        'href' => '/pm/todos', 'active' => ['pm/todos'],
    ],
    [
        'label' => 'Jobs', 'icon' => 'briefcase', 'permission' => 'nav.pm',
        'href' => '/pm/jobs', 'active' => ['pm/jobs', 'pm/jobs/*'],
    ],
    [
        'label' => 'Product Library', 'icon' => 'books', 'permission' => 'nav.pm',
        'href' => '/pm/library', 'active' => ['pm/library'],
    ],
    [
        'label' => 'Change History', 'icon' => 'history', 'permission' => 'nav.pm',
        'href' => '/pm/history', 'active' => ['pm/history'], 'action_permission' => 'pm.history.view',
    ],
    [
        'label' => 'Purchasing', 'icon' => 'shopping-cart', 'permission' => 'nav.pm',
        'active' => ['pm/vendors', 'pm/purchase-orders', 'pm/purchase-orders/*'],
        'items' => [
            ['label' => 'Job Purchase Orders', 'href' => '/pm/purchase-orders', 'nav' => 'nav.pm', 'active' => ['pm/purchase-orders', 'pm/purchase-orders/*']],
            ['label' => 'Vendors', 'href' => '/pm/vendors', 'nav' => 'nav.pm'],
        ],
    ],
];
