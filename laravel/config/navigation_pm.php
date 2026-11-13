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
        'label' => 'Jobs', 'icon' => 'briefcase', 'permission' => 'nav.pm',
        'href' => '/pm/jobs', 'active' => ['pm/jobs', 'pm/jobs/*'],
    ],
    [
        'label' => 'Purchasing', 'icon' => 'shopping-cart', 'permission' => 'nav.pm',
        'active' => ['pm/vendors'],
        'items' => [
            ['label' => 'Vendors', 'href' => '/pm/vendors', 'nav' => 'nav.pm'],
        ],
    ],
];
