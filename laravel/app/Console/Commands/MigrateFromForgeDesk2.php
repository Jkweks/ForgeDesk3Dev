<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;

class MigrateFromForgeDesk2 extends Command
{
    protected $signature = 'migrate:fd2
                            {dump_path : Path to the PostgreSQL dump file (.sql or .sql.gz)}
                            {--fresh : Drop and recreate all tables before migration}
                            {--dry-run : Parse and validate without inserting data}
                            {--skip-transactions : Skip inventory transaction migration (can be slow)}';

    protected $description = 'Migrate data from ForgeDesk2 PostgreSQL dump to ForgeDesk3';

    protected array $tables = [];
    protected int $migrationUserId;
    protected array $stats = [];
    protected array $useOptionPaths = [];
    protected bool $dryRun = false;

    public function handle(): int
    {
        $dumpPath = $this->argument('dump_path');
        $this->dryRun = $this->option('dry-run');

        if (!file_exists($dumpPath)) {
            $this->error("Dump file not found: {$dumpPath}");
            return 1;
        }

        $this->info('Starting ForgeDesk2 to ForgeDesk3 migration...');
        $this->newLine();

        // Parse the dump file
        $this->info('Parsing dump file...');
        $this->parseDumpFile($dumpPath);
        $this->info('Found ' . count($this->tables) . ' tables with data');
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No data will be inserted');
            $this->newLine();
        }

        // Optionally run fresh migrations
        if ($this->option('fresh') && !$this->dryRun) {
            $this->warn('Running fresh migrations...');
            $this->call('migrate:fresh', ['--force' => true]);
            $this->newLine();
        }

        // Create or find migration user
        $this->setupMigrationUser();

        // Build configurator use option paths
        $this->buildUseOptionPaths();

        // Run migrations in order
        $this->runMigrations();

        // Show summary
        $this->showSummary();

        return 0;
    }

    protected function parseDumpFile(string $path): void
    {
        // Handle gzipped files
        if (str_ends_with($path, '.gz')) {
            $handle = gzopen($path, 'r');
        } else {
            $handle = fopen($path, 'r');
        }

        if (!$handle) {
            throw new \RuntimeException("Cannot open file: {$path}");
        }

        $currentTable = null;
        $columns = [];
        $rows = [];

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            // Detect COPY statement
            if (preg_match('/^COPY public\.(\w+) \(([^)]+)\) FROM stdin;$/', $line, $matches)) {
                $currentTable = $matches[1];
                $columns = array_map('trim', explode(',', $matches[2]));
                $rows = [];
                continue;
            }

            // End of COPY data
            if ($line === '\\.' && $currentTable) {
                $this->tables[$currentTable] = [
                    'columns' => $columns,
                    'rows' => $rows,
                ];
                $currentTable = null;
                continue;
            }

            // Data row (tab-separated)
            if ($currentTable && $line !== '') {
                $values = explode("\t", $line);
                // Convert \N to null
                $values = array_map(fn($v) => $v === '\\N' ? null : $v, $values);
                $rows[] = array_combine($columns, $values);
            }
        }

        if (str_ends_with($path, '.gz')) {
            gzclose($handle);
        } else {
            fclose($handle);
        }
    }

    protected function setupMigrationUser(): void
    {
        if ($this->dryRun) {
            $this->migrationUserId = 1;
            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@forgedesk.local'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]
        );

        $this->migrationUserId = $user->id;
        $this->info("Using migration user ID: {$this->migrationUserId}");
    }

    protected function buildUseOptionPaths(): void
    {
        if (!isset($this->tables['configurator_part_use_options'])) {
            return;
        }

        $options = collect($this->tables['configurator_part_use_options']['rows'])
            ->keyBy('id');

        foreach ($options as $id => $option) {
            $chain = [];
            $current = $option;
            while ($current) {
                array_unshift($chain, $current['name']);
                $current = $current['parent_id'] ? $options->get($current['parent_id']) : null;
            }
            $this->useOptionPaths[$id] = implode(' > ', $chain);
        }

        $this->info('Built ' . count($this->useOptionPaths) . ' use option paths');
    }

    protected function runMigrations(): void
    {
        // Phase 1: Foundation tables
        $this->migrateSuppliers();
        $this->migrateMachineTypes();
        $this->migrateStorageLocations();

        // Phase 2: Categories (from systems)
        $this->migrateCategories();

        // Phase 3: Products
        $this->migrateProducts();
        $this->migrateCategoryProduct();

        // Phase 4: Inventory locations
        $this->migrateInventoryLocations();

        // Phase 4b: Inventory transactions (optional)
        if (!$this->option('skip-transactions')) {
            $this->migrateInventoryTransactions();
        }

        // Phase 5: Job reservations
        $this->migrateJobReservations();
        $this->migrateJobReservationItems();

        // Phase 6: Purchase orders
        $this->migratePurchaseOrders();
        $this->migratePurchaseOrderItems();

        // Phase 7: Cycle counts
        $this->migrateCycleCountSessions();
        $this->migrateCycleCountItems();

        // Phase 8: Maintenance
        $this->migrateMachines();
        $this->migrateAssets();
        $this->migrateAssetMachine();
        $this->migrateMaintenanceTasks();
        $this->migrateMaintenanceRecords();

        // Phase 9: Required parts
        $this->migrateRequiredParts();
    }

    protected function migrateSuppliers(): void
    {
        $tableName = 'suppliers';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('suppliers')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $row['name']), 0, 6)),
                'contact_name' => $row['contact_name'],
                'contact_email' => $row['contact_email'],
                'contact_phone' => $row['contact_phone'],
                'default_lead_time_days' => $row['default_lead_time_days'] ?? 0,
                'notes' => $row['notes'],
                'is_active' => true,
                'country' => 'USA',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('suppliers')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " suppliers");
    }

    protected function migrateMachineTypes(): void
    {
        $tableName = 'maintenance_machine_types';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating machine_types...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['machine_types'] = count($rows);
            return;
        }

        DB::table('machine_types')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('machine_types')->insert($inserts);
        $this->stats['machine_types'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " machine types");
    }

    protected function migrateStorageLocations(): void
    {
        $tableName = 'storage_locations';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('storage_locations')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $row['name']), 0, 10)),
                'description' => $row['description'],
                'type' => 'bin',
                'aisle' => $row['aisle'],
                'bay' => $row['rack'] ?? null,
                'level' => $row['shelf'] ?? null,
                'position' => $row['bin'] ?? null,
                'is_active' => $row['is_active'] === 't' || $row['is_active'] === true || $row['is_active'] === '1',
                'sort_order' => $row['sort_order'] ?? 0,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('storage_locations')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " storage locations");
    }

    protected function migrateCategories(): void
    {
        $tableName = 'inventory_systems';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating categories (from inventory_systems)...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['categories'] = count($rows);
            return;
        }

        DB::table('categories')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $description = '';
            if ($row['manufacturer']) {
                $description .= "Manufacturer: {$row['manufacturer']}";
            }
            if (isset($row['system_type']) && $row['system_type']) {
                $description .= ($description ? ' | ' : '') . "Type: {$row['system_type']}";
            }

            // Use system field as code (unique), fallback to id-prefixed name
            $code = $row['system']
                ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $row['system']))
                : 'CAT' . $row['id'];

            $inserts[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'code' => substr($code, 0, 20),
                'system' => $row['system'] ?? null,
                'description' => $description ?: null,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $row['created_at'],
                'updated_at' => now(),
            ];
        }

        DB::table('categories')->insert($inserts);
        $this->stats['categories'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " categories");
    }

    protected function migrateProducts(): void
    {
        $tableName = 'inventory_items';
        if (!isset($this->tables[$tableName])) {
            $this->error("No data for {$tableName} - this is required!");
            return;
        }

        $this->info("Migrating products (from inventory_items)...");
        $rows = $this->tables[$tableName]['rows'];

        // Build configurator profile map
        $profiles = [];
        if (isset($this->tables['configurator_part_profiles'])) {
            foreach ($this->tables['configurator_part_profiles']['rows'] as $profile) {
                $profiles[$profile['inventory_item_id']] = $profile;
            }
        }

        // Build use option links map
        $useLinks = [];
        if (isset($this->tables['configurator_part_use_links'])) {
            foreach ($this->tables['configurator_part_use_links']['rows'] as $link) {
                $itemId = $link['inventory_item_id'];
                $optionId = $link['use_option_id'];
                if (!isset($useLinks[$itemId])) {
                    $useLinks[$itemId] = [];
                }
                if (isset($this->useOptionPaths[$optionId])) {
                    $useLinks[$itemId][] = $this->useOptionPaths[$optionId];
                }
            }
        }

        if ($this->dryRun) {
            $this->stats['products'] = count($rows);
            return;
        }

        DB::table('products')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $profile = $profiles[$row['id']] ?? null;
            $usePaths = $useLinks[$row['id']] ?? [];

            $inserts[] = [
                'id' => $row['id'],
                'sku' => $row['sku'],
                'part_number' => $row['part_number'] ?: null,
                'finish' => $row['finish'],
                'description' => $row['item'],
                'location' => $row['location'],
                'quantity_on_hand' => (int) ($row['stock'] ?? 0),
                'quantity_committed' => (int) ($row['committed_qty'] ?? 0),
                'on_order_qty' => (int) floatval($row['on_order_qty'] ?? 0),
                'safety_stock' => (int) floatval($row['safety_stock'] ?? 0),
                'min_order_qty' => (int) floatval($row['min_order_qty'] ?? 0),
                'order_multiple' => (int) floatval($row['order_multiple'] ?? 0),
                'pack_size' => max(1, (int) floatval($row['pack_size'] ?? 1)),
                'purchase_uom' => $row['purchase_uom'] ?: 'EA',
                'stock_uom' => $row['stock_uom'] ?: 'EA',
                'unit_of_measure' => 'EA',
                'status' => $this->mapStatus($row['status'] ?? 'In Stock'),
                'supplier_id' => $row['supplier_id'] ?: null,
                'supplier_contact' => $row['supplier_contact'],
                'supplier_sku' => $row['supplier_sku'],
                'reorder_point' => (int) ($row['reorder_point'] ?? 0),
                'lead_time_days' => (int) ($row['lead_time_days'] ?? 0),
                'average_daily_use' => $row['average_daily_use'] ? floatval($row['average_daily_use']) : null,
                'unit_cost' => 0,
                'unit_price' => 0,
                'minimum_quantity' => 0,
                'is_active' => true,
                'is_discontinued' => false,
                // Configurator fields
                'configurator_available' => $profile && ($profile['is_enabled'] === 't' || $profile['is_enabled'] === true),
                'configurator_type' => $profile['part_type'] ?? null,
                'configurator_use_path' => !empty($usePaths) ? implode('; ', $usePaths) : null,
                'dimension_height' => $profile['height_lz'] ?? null,
                'dimension_depth' => $profile['depth_ly'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($inserts, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        $this->stats['products'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " products");
    }

    protected function migrateCategoryProduct(): void
    {
        $tableName = 'inventory_item_systems';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating category_product (from inventory_item_systems)...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['category_product'] = count($rows);
            return;
        }

        DB::table('category_product')->truncate();

        // Track first category per product to mark as primary
        $primarySet = [];
        $inserts = [];

        foreach ($rows as $row) {
            $productId = $row['inventory_item_id'];
            $isPrimary = !isset($primarySet[$productId]);
            $primarySet[$productId] = true;

            $inserts[] = [
                'product_id' => $productId,
                'category_id' => $row['system_id'],
                'is_primary' => $isPrimary,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('category_product')->insert($inserts);
        $this->stats['category_product'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " category-product links");
    }

    protected function migrateInventoryLocations(): void
    {
        $tableName = 'inventory_item_locations';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating inventory_locations...");
        $rows = $this->tables[$tableName]['rows'];

        // Build storage location name map
        $storageNames = [];
        if (isset($this->tables['storage_locations'])) {
            foreach ($this->tables['storage_locations']['rows'] as $loc) {
                $storageNames[$loc['id']] = $loc['name'];
            }
        }

        if ($this->dryRun) {
            $this->stats['inventory_locations'] = count($rows);
            return;
        }

        DB::table('inventory_locations')->truncate();

        $primarySet = [];
        $inserts = [];

        foreach ($rows as $row) {
            $productId = $row['inventory_item_id'];
            $locationName = $storageNames[$row['storage_location_id']] ?? "Location #{$row['storage_location_id']}";
            $isPrimary = !isset($primarySet[$productId]);
            $primarySet[$productId] = true;

            $inserts[] = [
                'id' => $row['id'],
                'product_id' => $productId,
                'location' => $locationName,
                'quantity' => (int) ($row['quantity'] ?? 0),
                'quantity_committed' => 0,
                'is_primary' => $isPrimary,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('inventory_locations')->insert($inserts);
        $this->stats['inventory_locations'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " inventory locations");
    }

    protected function migrateInventoryTransactions(): void
    {
        $linesTable = 'inventory_transaction_lines';
        $headersTable = 'inventory_transactions';

        if (!isset($this->tables[$linesTable]) || !isset($this->tables[$headersTable])) {
            $this->warn("No data for inventory transactions");
            return;
        }

        $this->info("Migrating inventory_transactions (flattening header/lines)...");

        $headers = collect($this->tables[$headersTable]['rows'])->keyBy('id');
        $lines = $this->tables[$linesTable]['rows'];

        if ($this->dryRun) {
            $this->stats['inventory_transactions'] = count($lines);
            return;
        }

        DB::table('inventory_transactions')->truncate();

        $inserts = [];
        foreach ($lines as $line) {
            $header = $headers->get($line['transaction_id']);
            if (!$header) continue;

            $type = $this->inferTransactionType($header['reference'] ?? '');

            $inserts[] = [
                'id' => $line['id'],
                'product_id' => $line['inventory_item_id'],
                'type' => $type,
                'quantity' => (int) $line['quantity_change'],
                'quantity_before' => (int) ($line['stock_before'] ?? 0),
                'quantity_after' => (int) ($line['stock_after'] ?? 0),
                'reference_number' => $header['reference'],
                'notes' => trim(($line['note'] ?? '') . ' ' . ($header['notes'] ?? '')) ?: null,
                'user_id' => $this->migrationUserId,
                'transaction_date' => $header['created_at'],
                'created_at' => $header['created_at'],
                'updated_at' => $header['created_at'],
            ];
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            DB::table('inventory_transactions')->insert($chunk);
        }

        $this->stats['inventory_transactions'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " inventory transactions");
    }

    protected function migrateJobReservations(): void
    {
        $tableName = 'job_reservations';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('job_reservations')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'job_number' => $row['job_number'],
                'release_number' => $row['release_number'] ?? 1,
                'job_name' => $row['job_name'],
                'requested_by' => $row['requested_by'],
                'needed_by' => $row['needed_by'],
                'status' => $this->mapReservationStatus($row['status'] ?? 'draft'),
                'notes' => $row['notes'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('job_reservations')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " job reservations");
    }

    protected function migrateJobReservationItems(): void
    {
        $tableName = 'job_reservation_items';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('job_reservation_items')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'reservation_id' => $row['reservation_id'],
                'product_id' => $row['inventory_item_id'],
                'requested_qty' => (int) ($row['requested_qty'] ?? 0),
                'committed_qty' => (int) ($row['committed_qty'] ?? 0),
                'consumed_qty' => (int) ($row['consumed_qty'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('job_reservation_items')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " job reservation items");
    }

    protected function migratePurchaseOrders(): void
    {
        $tableName = 'purchase_orders';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('purchase_orders')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            // Generate fallback PO number if missing
            $poNumber = $row['order_number'] ?: 'FD2-PO-' . $row['id'];

            $inserts[] = [
                'id' => $row['id'],
                'po_number' => $poNumber,
                'supplier_id' => $row['supplier_id'] ?: null,
                'status' => $this->mapPoStatus($row['status'] ?? 'draft'),
                'order_date' => $row['order_date'],
                'expected_date' => $row['expected_date'],
                'total_amount' => floatval($row['total_cost'] ?? 0),
                'notes' => $row['notes'],
                'created_by' => $this->migrationUserId,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('purchase_orders')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " purchase orders");
    }

    protected function migratePurchaseOrderItems(): void
    {
        $tableName = 'purchase_order_lines';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating purchase_order_items (from purchase_order_lines)...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['purchase_order_items'] = count($rows);
            return;
        }

        DB::table('purchase_order_items')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $qtyOrdered = (int) floatval($row['quantity_ordered'] ?? 0);
            $unitCost = floatval($row['unit_cost'] ?? 0);

            $inserts[] = [
                'id' => $row['id'],
                'purchase_order_id' => $row['purchase_order_id'],
                'product_id' => $row['inventory_item_id'] ?: null,
                'quantity_ordered' => $qtyOrdered,
                'quantity_received' => (int) floatval($row['quantity_received'] ?? 0),
                'unit_cost' => $unitCost,
                'total_cost' => $qtyOrdered * $unitCost,
                'notes' => $row['description'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('purchase_order_items')->insert($inserts);
        $this->stats['purchase_order_items'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " purchase order items");
    }

    protected function migrateCycleCountSessions(): void
    {
        $tableName = 'cycle_count_sessions';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('cycle_count_sessions')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $status = match ($row['status'] ?? 'in_progress') {
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                default => 'in_progress',
            };

            // Truncate location to fit VARCHAR(255)
            $location = $row['location_filter'];
            if ($location && strlen($location) > 255) {
                $location = substr($location, 0, 252) . '...';
            }

            // Append ID to session_number to ensure uniqueness
            $sessionNumber = $row['name'] . ' (#' . $row['id'] . ')';

            $inserts[] = [
                'id' => $row['id'],
                'session_number' => $sessionNumber,
                'location' => $location,
                'status' => $status,
                'scheduled_date' => substr($row['started_at'], 0, 10),
                'started_at' => $row['started_at'],
                'completed_at' => $row['completed_at'],
                'assigned_to' => $this->migrationUserId,
                'created_at' => $row['started_at'],
                'updated_at' => $row['completed_at'] ?? $row['started_at'],
            ];
        }

        DB::table('cycle_count_sessions')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " cycle count sessions");
    }

    protected function migrateCycleCountItems(): void
    {
        $tableName = 'cycle_count_lines';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating cycle_count_items (from cycle_count_lines)...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['cycle_count_items'] = count($rows);
            return;
        }

        DB::table('cycle_count_items')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $isSkipped = $row['is_skipped'] === 't' || $row['is_skipped'] === true || $row['is_skipped'] === '1';
            $hasCounted = $row['counted_qty'] !== null;

            $varianceStatus = 'pending';
            if ($isSkipped) {
                $varianceStatus = 'rejected';
            } elseif ($hasCounted) {
                $variance = (int) ($row['variance'] ?? 0);
                $varianceStatus = $variance === 0 ? 'approved' : 'requires_review';
            }

            $inserts[] = [
                'id' => $row['id'],
                'session_id' => $row['session_id'],
                'product_id' => $row['inventory_item_id'],
                'system_quantity' => (int) ($row['expected_qty'] ?? 0),
                'counted_quantity' => $row['counted_qty'] !== null ? (int) $row['counted_qty'] : null,
                'variance' => (int) ($row['variance'] ?? 0),
                'variance_status' => $varianceStatus,
                'count_notes' => $row['note'],
                'counted_by' => $hasCounted ? $this->migrationUserId : null,
                'counted_at' => $row['counted_at'],
                'adjustment_created' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('cycle_count_items')->insert($inserts);
        $this->stats['cycle_count_items'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " cycle count items");
    }

    protected function migrateMachines(): void
    {
        $tableName = 'maintenance_machines';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating machines...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['machines'] = count($rows);
            return;
        }

        DB::table('machines')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'equipment_type' => $row['equipment_type'],
                'machine_type_id' => $row['machine_type_id'] ?: null,
                'manufacturer' => $row['manufacturer'],
                'model' => $row['model'],
                'serial_number' => $row['serial_number'],
                'location' => $row['location'],
                'documents' => $row['documents'] ?? '[]',
                'notes' => $row['notes'],
                'total_downtime_minutes' => 0,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('machines')->insert($inserts);
        $this->stats['machines'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " machines");
    }

    protected function migrateAssets(): void
    {
        $tableName = 'maintenance_assets';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating assets...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['assets'] = count($rows);
            return;
        }

        DB::table('assets')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'documents' => $row['documents'] ?? '[]',
                'notes' => $row['notes'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('assets')->insert($inserts);
        $this->stats['assets'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " assets");
    }

    protected function migrateAssetMachine(): void
    {
        $tableName = 'maintenance_asset_machines';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating asset_machine...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['asset_machine'] = count($rows);
            return;
        }

        DB::table('asset_machine')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'asset_id' => $row['asset_id'],
                'machine_id' => $row['machine_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('asset_machine')->insert($inserts);
        $this->stats['asset_machine'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " asset-machine links");
    }

    protected function migrateMaintenanceTasks(): void
    {
        $tableName = 'maintenance_tasks';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('maintenance_tasks')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'machine_id' => $row['machine_id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'frequency' => $row['frequency'],
                'assigned_to' => $this->migrationUserId,
                'interval_count' => $row['interval_count'] ? (int) $row['interval_count'] : null,
                'interval_unit' => $row['interval_unit'],
                'start_date' => $row['start_date'],
                'status' => $row['status'] ?? 'active',
                'priority' => $row['priority'] ?? 'medium',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        DB::table('maintenance_tasks')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " maintenance tasks");
    }

    protected function migrateMaintenanceRecords(): void
    {
        $tableName = 'maintenance_records';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating {$tableName}...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats[$tableName] = count($rows);
            return;
        }

        DB::table('maintenance_records')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $inserts[] = [
                'id' => $row['id'],
                'machine_id' => $row['machine_id'],
                'task_id' => $row['task_id'] ?: null,
                'asset_id' => $row['asset_id'] ?: null,
                'performed_by' => $this->migrationUserId,
                'performed_at' => $row['performed_at'],
                'notes' => $row['notes'],
                'downtime_minutes' => $row['downtime_minutes'] ? (int) $row['downtime_minutes'] : null,
                'labor_hours' => $row['labor_hours'] ? floatval($row['labor_hours']) : null,
                'parts_used' => $row['parts_used'] ?? '[]',
                'attachments' => $row['attachments'] ?? '[]',
                'created_at' => $row['created_at'],
                'updated_at' => $row['created_at'],
            ];
        }

        DB::table('maintenance_records')->insert($inserts);
        $this->stats[$tableName] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " maintenance records");

        // Update machine downtime totals
        $this->info("  Updating machine downtime totals...");
        DB::statement('
            UPDATE machines
            SET total_downtime_minutes = (
                SELECT COALESCE(SUM(downtime_minutes), 0)
                FROM maintenance_records
                WHERE maintenance_records.machine_id = machines.id
            )
        ');
    }

    protected function migrateRequiredParts(): void
    {
        $tableName = 'configurator_part_requirements';
        if (!isset($this->tables[$tableName])) {
            $this->warn("No data for {$tableName}");
            return;
        }

        $this->info("Migrating required_parts (from configurator_part_requirements)...");
        $rows = $this->tables[$tableName]['rows'];

        if ($this->dryRun) {
            $this->stats['required_parts'] = count($rows);
            return;
        }

        DB::table('required_parts')->truncate();

        $inserts = [];
        foreach ($rows as $row) {
            $finishPolicy = match ($row['finish_policy'] ?? 'fixed') {
                'fixed' => 'specific',
                'match_parent' => 'match_parent',
                default => $row['finish_policy'] ?? 'specific',
            };

            $inserts[] = [
                'id' => $row['id'] ?? null,
                'parent_product_id' => $row['inventory_item_id'],
                'required_product_id' => $row['required_inventory_item_id'],
                'quantity' => (int) ($row['quantity'] ?? 1),
                'finish_policy' => $finishPolicy,
                'specific_finish' => $row['fixed_finish'],
                'sort_order' => 0,
                'is_optional' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('required_parts')->insert($inserts);
        $this->stats['required_parts'] = count($inserts);
        $this->info("  Migrated " . count($inserts) . " required parts");
    }

    protected function mapStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'in stock' => 'in_stock',
            'low stock', 'low' => 'low_stock',
            'critical' => 'critical',
            'out of stock' => 'out_of_stock',
            default => 'in_stock',
        };
    }

    protected function mapReservationStatus(string $status): string
    {
        return match ($status) {
            'committed' => 'active',
            'draft', 'active', 'in_progress', 'fulfilled', 'on_hold', 'cancelled' => $status,
            default => 'active',
        };
    }

    protected function mapPoStatus(string $status): string
    {
        return match ($status) {
            'sent' => 'submitted',
            'closed' => 'received',
            'draft', 'partially_received', 'cancelled' => $status,
            default => 'draft',
        };
    }

    protected function inferTransactionType(string $reference): string
    {
        $ref = strtoupper($reference);
        if (str_starts_with($ref, 'PO-') || str_contains($ref, 'RECEIPT')) {
            return 'receipt';
        }
        if (str_starts_with($ref, 'CC-') || str_contains($ref, 'CYCLE')) {
            return 'cycle_count';
        }
        if (str_contains($ref, 'SHIP')) {
            return 'shipment';
        }
        if (str_contains($ref, 'TRANSFER')) {
            return 'transfer';
        }
        if (str_contains($ref, 'RETURN')) {
            return 'return';
        }
        if (str_contains($ref, 'JOB') || str_contains($ref, 'ISSUE')) {
            return 'job_issue';
        }
        return 'adjustment';
    }

    protected function showSummary(): void
    {
        $this->newLine();
        $this->info('=' . str_repeat('=', 50));
        $this->info('MIGRATION SUMMARY' . ($this->dryRun ? ' (DRY RUN)' : ''));
        $this->info('=' . str_repeat('=', 50));

        $headers = ['Table', 'Records'];
        $rows = [];
        $total = 0;

        foreach ($this->stats as $table => $count) {
            $rows[] = [$table, number_format($count)];
            $total += $count;
        }

        $this->table($headers, $rows);
        $this->info("Total records: " . number_format($total));
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('This was a dry run. No data was inserted.');
            $this->info('Run without --dry-run to perform actual migration.');
        } else {
            $this->info('Migration completed successfully!');
        }
    }
}
