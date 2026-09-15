<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\BackupRun;
use App\Models\Category;
use App\Models\CycleCountSession;
use App\Models\InventoryTransaction;
use App\Models\JobReservation;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            'backups' => $this->getBackupStats(),
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
                $size = DB::select('SELECT pg_database_size(?) as size', [$dbName]);
                $dbInfo['size'] = $size[0]->size ?? 0;
                $dbInfo['size_human'] = $this->formatBytes($dbInfo['size']);

                // Table sizes
                $tables = DB::select('
                    SELECT relname as table_name,
                           n_live_tup as row_count,
                           pg_total_relation_size(quote_ident(relname)) as total_size
                    FROM pg_stat_user_tables
                    ORDER BY pg_total_relation_size(quote_ident(relname)) DESC
                ');

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

                $size = DB::select('
                    SELECT SUM(data_length + index_length) as size
                    FROM information_schema.tables
                    WHERE table_schema = ?
                ', [$dbName]);
                $dbInfo['size'] = $size[0]->size ?? 0;
                $dbInfo['size_human'] = $this->formatBytes($dbInfo['size']);

                $tables = DB::select('
                    SELECT table_name, table_rows as row_count,
                           (data_length + index_length) as total_size
                    FROM information_schema.tables
                    WHERE table_schema = ?
                    ORDER BY (data_length + index_length) DESC
                ', [$dbName]);

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
        $diskFree = @disk_free_space($storagePath);
        $diskTotal = @disk_total_space($storagePath);
        $diskFree = ($diskFree !== false) ? $diskFree : 0;
        $diskTotal = ($diskTotal !== false) ? $diskTotal : 0;
        $diskUsedPercent = ($diskTotal > 0) ? round((1 - $diskFree / $diskTotal) * 100, 1) : 0;
        $services['storage'] = [
            'status' => is_writable($storagePath) ? 'operational' : 'error',
            'disk_free' => $diskFree,
            'disk_free_human' => $this->formatBytes($diskFree),
            'disk_total' => $diskTotal,
            'disk_total_human' => $this->formatBytes($diskTotal),
            'disk_used_percent' => $diskUsedPercent,
        ];

        return $services;
    }

    private function getInventoryStats()
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'in_stock' => Product::where('status', 'in_stock')->count(),
            'low_stock' => Product::whereIn('status', ['low', 'very_low'])->count(),
            'very_low' => Product::where('status', 'very_low')->count(),
            'critical' => Product::where('status', 'critical')->count(),
            'out_of_stock' => Product::where('status', 'out_of_stock')->count(),
            'on_order' => Product::where('status', 'on_order')->count(),
            'categories' => Category::count(),
            'suppliers' => Supplier::count(),
            'storage_locations' => StorageLocation::count(),
            'transactions_today' => InventoryTransaction::whereDate('created_at', today())->count(),
            'transactions_this_week' => InventoryTransaction::where('created_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    private function getOperationsStats()
    {
        $safe = function (callable $fn, $fallback = null) {
            try {
                return $fn();
            } catch (\Exception $e) {
                return $fallback;
            }
        };

        return [
            'purchase_orders' => [
                'total' => $safe(fn () => PurchaseOrder::count(), 0),
                'open' => $safe(fn () => PurchaseOrder::whereIn('status', ['draft', 'submitted', 'approved'])->count(), 0),
            ],
            'job_reservations' => [
                'total' => $safe(fn () => JobReservation::count(), 0),
                'active' => $safe(fn () => JobReservation::whereIn('status', ['active', 'in_progress', 'on_hold'])->count(), 0),
            ],
            'cycle_counts' => [
                'total' => $safe(fn () => CycleCountSession::count(), 0),
                'active' => $safe(fn () => CycleCountSession::whereIn('status', ['planned', 'in_progress'])->count(), 0),
            ],
            'maintenance' => [
                'machines' => $safe(fn () => Machine::count(), 0),
                'assets' => $safe(fn () => Asset::count(), 0),
                'active_tasks' => $safe(fn () => MaintenanceTask::where('status', 'active')->count(), 0),
                'total_records' => $safe(fn () => MaintenanceRecord::count(), 0),
                'last_service' => $safe(fn () => MaintenanceRecord::latest('performed_at')->value('performed_at')),
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

    /**
     * Backup health for the status page: today-back-N daily bars (worst
     * status per calendar day, red > orange > yellow > green) plus the most
     * recent run and how long it's been since the last fully successful one.
     * Populated by backup.sh (repo root) via `php artisan backup:record` —
     * see RecordBackupRun for the consumer side.
     */
    private function getBackupStats()
    {
        $days = 60;
        $severity = ['failed' => 3, 'remote_failed' => 2, 'local_failed' => 1, 'success' => 0];

        try {
            $since = now()->subDays($days - 1)->toDateString();

            $runs = BackupRun::where('run_date', '>=', $since)
                ->orderBy('run_date')
                ->orderBy('started_at')
                ->get();

            // Worst status per calendar day, in case more than one run happened.
            $byDate = [];
            foreach ($runs as $run) {
                $date = $run->run_date->toDateString();
                if (! isset($byDate[$date]) || $severity[$run->status] > $severity[$byDate[$date]['status']]) {
                    $byDate[$date] = [
                        'status' => $run->status,
                        'started_at' => $run->started_at?->toIso8601String(),
                        'components' => $run->components,
                        'error_message' => $run->error_message,
                    ];
                }
            }

            $history = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $history[] = array_merge(['date' => $date, 'status' => null], $byDate[$date] ?? []);
            }

            $latest = BackupRun::latest('started_at')->first();
            $lastSuccess = BackupRun::where('status', 'success')->latest('started_at')->first();

            return [
                'latest' => $latest ? [
                    'status' => $latest->status,
                    'started_at' => $latest->started_at?->toIso8601String(),
                    'finished_at' => $latest->finished_at?->toIso8601String(),
                    'components' => $latest->components,
                    'error_message' => $latest->error_message,
                ] : null,
                'last_success_at' => $lastSuccess?->started_at?->toIso8601String(),
                'hours_since_last_success' => $lastSuccess ? now()->diffInHours($lastSuccess->started_at) : null,
                'history' => $history,
            ];
        } catch (\Exception $e) {
            return [
                'latest' => null,
                'last_success_at' => null,
                'hours_since_last_success' => null,
                'history' => [],
                'error' => $e->getMessage(),
            ];
        }
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
        if ($days > 0) {
            $parts[] = $days.'d';
        }
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        $parts[] = $minutes.'m';

        return implode(' ', $parts);
    }

    private function formatBytes($bytes)
    {
        if ($bytes === null || $bytes == 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }
}
