<?php
// ============================================================
// /api/users.php  — lightweight user list for dropdowns
// ============================================================
require_once __DIR__ . '/config.php';
method('GET');
$stmt = db()->query("SELECT id, name, initials, role FROM fd_users WHERE active = TRUE ORDER BY name");
json_ok($stmt->fetchAll());
