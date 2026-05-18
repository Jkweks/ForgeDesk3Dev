<?php
// ============================================================
// ForgeDeskDev — Fab Schedule Module
// config.php — DB connection + shared helpers
// Slot into forgedesk3dev: require_once 'fab/api/config.php';
// ============================================================

define('DB_HOST', getenv('PGHOST')     ?: 'localhost');
define('DB_PORT', getenv('PGPORT')     ?: '5432');
define('DB_NAME', getenv('PGDATABASE') ?: 'forgedesk');
define('DB_USER', getenv('PGUSER')     ?: 'forgedesk');
define('DB_PASS', getenv('PGPASSWORD') ?: '');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Response helpers ──────────────────────────────────────────
function json_ok(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_err(string $message, int $status = 400): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function method(string ...$allowed): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        json_err('Method not allowed', 405);
    }
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

function require_param(array $data, string ...$keys): void {
    foreach ($keys as $k) {
        if (!isset($data[$k]) || $data[$k] === '') {
            json_err("Missing required field: $k", 422);
        }
    }
}

// ── CORS (adjust origin for prod) ────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
