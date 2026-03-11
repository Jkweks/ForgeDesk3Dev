<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Machine;
use App\Models\Asset;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceRecord;
use App\Models\Supplier;
use App\Models\Category;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\JobReservation;
use App\Models\CycleCountSession;
use App\Models\InventoryTransaction;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatusController extends Controller
{
    public function index()
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        return response()->json([
            'application' => $this->getApplicationInfo($startTime),
            'database' => $this->getDatabaseInfo(),
            'services' => $this->getServicesStatus(),
            'inventory' => $this->getInventoryStats(),
            'operations' => $this->getOperationsStats(),
            'users' => $this->getUserStats(),
        ]);
    }

    private function getApplicationInfo($startTime)
    {
        return [
            'name' => config('app.name', 'ForgeDesk'),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'timezone' => config('app.timezone'),
            'server_time' => now()->toIso8601String(),
            'uptime' => $this->getServerUptime(),
        ];
    }

    private function getDatabaseInfo()
    {
        $dbInfo = [
            'connection' => config('database.default'),
            'status' => 'unknown',
            'size' => null,
            'tables' => [],
        ];

        try {
            DB::connection()->getPdo();
            $dbInfo['status'] = 'connected';

            $driver = config('database.default');

            if ($driver === 'pgsql') {
                $dbName = config('database.connections.pgsql.database');

                // Database size
                $size = DB::select("SELECT pg_database_size(?) as size", [$dbName]);
                $dbInfo['size'] = $size[0]->size ?? 0;
                $dbInfo['size_human'] = $this->formatBytes($dbInfo['size']);

                // Table sizes
                $tables = DB::select("
                    SELECT relname as table_name,
                           n_live_tup as row_count,
                           pg_total_relation_size(quote_ident(relname)) as total_size
                    FROM pg_stat_user_tables
                    ORDER BY pg_total_relation_size(quote_ident(relname)) DESC
                ");

                $dbInfo['tables'] = collect($tables)->map(function ($table) {
                    return [
                        'name' => $table->table_name,
                        'rows' => (int) $table->row_count,
                        'size' => (int) $table->total_size,
                        'size_human' => $this->formatBytes($table->total_size),
                    ];
                })->toArray();

            } elseif ($driver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if (file_exists($dbPath)) {
                    $dbInfo['size'] = filesize($dbPath);
                    $dbInfo['size_human'] = $this->formatBytes($dbInfo['size']);
                }

                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                $dbInfo['tables'] = collect($tables)->map(function ($table) {
                    $count = DB::table($table->name)->count();
                    return [
                        'name' => $table->name,
                        'rows' => $count,
                        'size' => null,
                        'size_human' => null,
                    ];
                })->toArray();

            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                $dbName = config("database.connections.{$driver}.database");

                $size = DB::select("
                    SELECT SUM(data_length + index_length) as size
                    FROM information_schema.tables
                    WHERE table_schema = ?
                ", [$dbName]);
                $dbInfo['size'] = $size[0]->size ?? 0;
                $dbInfo['size_human'] = $this->formatBytes($dbInfo['size']);

                $tables = DB::select("
                    SELECT table_name, table_rows as row_count,
                           (data_length + index_length) as total_size
                    FROM information_schema.tables
                    WHERE table_schema = ?
                    ORDER BY (data_length + index_length) DESC
                ", [$dbName]);

                $dbInfo['tables'] = collect($tables)->map(function ($table) {
                    return [
                        'name' => $table->table_name,
                        'rows' => (int) $table->row_count,
                        'size' => (int) $table->total_size,
                        'size_human' => $this->formatBytes($table->total_size),
                    ];
                })->toArray();
            }
        } catch (\Exception $e) {
            $dbInfo['status'] = 'error';
            $dbInfo['error'] = $e->getMessage();
        }

        return $dbInfo;
    }

    private function getServicesStatus()
    {
        $services = [];

        // Cache / Redis
        $cacheDriver = config('cache.default');
        $services['cache'] = [
            'driver' => $cacheDriver,
            'status' => 'unknown',
        ];
        try {
            Cache::store($cacheDriver)->put('status_check', true, 10);
            $services['cache']['status'] = Cache::store($cacheDriver)->get('status_check') ? 'operational' : 'degraded';
            Cache::store($cacheDriver)->forget('status_check');
        } catch (\Exception $e) {
            $services['cache']['status'] = 'error';
            $services['cache']['error'] = $e->getMessage();
        }

        // Session driver
        $services['session'] = [
            'driver' => config('session.driver'),
            'status' => 'operational',
        ];

        // Queue
        $services['queue'] = [
            'driver' => config('queue.default'),
            'status' => 'operational',
        ];
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $services['queue']['pending_jobs'] = $pendingJobs;
            $services['queue']['failed_jobs'] = $failedJobs;
            if ($failedJobs > 0) {
                $services['queue']['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            // Queue tables may not exist
            $services['queue']['status'] = 'unknown';
        }

        // Storage
        $storagePath = storage_path();
        $services['storage'] = [
            'status' => is_writable($storagePath) ? 'operational' : 'error',
            'disk_free' => disk_free_space($storagePath),
            'disk_free_human' => $this->formatBytes(disk_free_space($storagePath)),
            'disk_total' => disk_total_space($storagePath),
            'disk_total_human' => $this->formatBytes(disk_total_space($storagePath)),
            'disk_used_percent' => round((1 - disk_free_space($storagePath) / disk_total_space($storagePath)) * 100, 1),
        ];

        return $services;
    }

    private function getInventoryStats()
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'in_stock' => Product::where('status', 'in_stock')->count(),
            'low_stock' => Product::where('status', 'low_stock')->count(),
            'critical' => Product::where('status', 'critical')->count(),
            'categories' => Category::count(),
            'suppliers' => Supplier::count(),
            'storage_locations' => StorageLocation::count(),
            'transactions_today' => InventoryTransaction::whereDate('created_at', today())->count(),
            'transactions_this_week' => InventoryTransaction::where('created_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    private function getOperationsStats()
    {
        return [
            'purchase_orders' => [
                'total' => PurchaseOrder::count(),
                'open' => PurchaseOrder::whereIn('status', ['draft', 'submitted', 'approved'])->count(),
            ],
            'job_reservations' => [
                'total' => JobReservation::count(),
                'active' => JobReservation::whereIn('status', ['active', 'in_progress', 'on_hold'])->count(),
            ],
            'cycle_counts' => [
                'total' => CycleCountSession::count(),
                'active' => CycleCountSession::whereIn('status', ['planned', 'in_progress'])->count(),
            ],
            'maintenance' => [
                'machines' => Machine::count(),
                'assets' => Asset::count(),
                'active_tasks' => MaintenanceTask::where('status', 'active')->count(),
                'total_records' => MaintenanceRecord::count(),
                'last_service' => MaintenanceRecord::latest('performed_at')->value('performed_at'),
            ],
        ];
    }

    private function getUserStats()
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'logged_in_recently' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function getServerUptime()
    {
        try {
            if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
                $uptime = (float) explode(' ', file_get_contents('/proc/uptime'))[0];
                return $this->formatUptime($uptime);
            }
        } catch (\Exception $e) {
            // ignore
        }
        return null;
    }

    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . 'd';
        if ($hours > 0) $parts[] = $hours . 'h';
        $parts[] = $minutes . 'm';

        return implode(' ', $parts);
    }

    private function formatBytes($bytes)
    {
        if ($bytes === null || $bytes == 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
