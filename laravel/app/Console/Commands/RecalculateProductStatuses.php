<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class RecalculateProductStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:recalculate-statuses {--sku=* : Specific SKUs to recalculate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate inventory status for all products or specific SKUs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $skus = $this->option('sku');

        if (!empty($skus)) {
            // Recalculate specific SKUs
            $this->info("Recalculating status for SKUs: " . implode(', ', $skus));

            $products = Product::whereIn('sku', $skus)->get();

            if ($products->isEmpty()) {
                $this->error('No products found with the specified SKUs');
                return 1;
            }

            $this->recalculateProducts($products);
        } else {
            // Recalculate all products
            $this->info('Recalculating status for all products...');

            $totalCount = Product::count();
            $this->info("Found {$totalCount} products");

            // Process in chunks for better memory management
            Product::chunk(100, function ($products) {
                $this->recalculateProducts($products);
            });
        }

        $this->info('✓ Status recalculation complete!');
        return 0;
    }

    /**
     * Recalculate statuses for a collection of products
     */
    protected function recalculateProducts($products)
    {
        $statusCounts = [
            'in_stock' => 0,
            'low_stock' => 0,
            'critical' => 0,
        ];

        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        foreach ($products as $product) {
            $oldStatus = $product->status;
            $product->updateStatus();
            $newStatus = $product->status;

            $statusCounts[$newStatus]++;

            if ($oldStatus !== $newStatus) {
                $this->newLine();
                $this->line("  {$product->sku}: {$oldStatus} → {$newStatus}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show summary
        $this->table(
            ['Status', 'Count'],
            [
                ['In Stock', $statusCounts['in_stock']],
                ['Low Stock', $statusCounts['low_stock']],
                ['Critical', $statusCounts['critical']],
            ]
        );
    }
}
