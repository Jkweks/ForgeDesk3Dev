<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Applies a reviewed roll-length sheet (sku, roll_feet, ...) to products: marks them length-based and
 * stores the roll length in inches, so gaskets/weatherstrip reserve as a fraction of a roll instead of
 * "one each". Dry run unless --apply. Rows with a blank roll_feet are skipped.
 */
class ApplyRollLengths extends Command
{
    protected $signature = 'products:apply-roll-lengths {csv : CSV with at least sku and roll_feet columns} {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Set length-based roll lengths on gasket/weatherstrip products from a reviewed CSV';

    public function handle(): int
    {
        $path = $this->argument('csv');
        if (! is_file($path)) {
            $this->error("Not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = array_map('trim', fgetcsv($handle, escape: ''));
        $changed = $skipped = 0;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $data = array_combine($header, array_pad($row, count($header), ''));
            $feet = trim((string) ($data['roll_feet'] ?? ''));
            if ($feet === '' || ! is_numeric($feet)) {
                $skipped++;

                continue;
            }

            $product = Product::where('sku', trim($data['sku']))->first();
            if (! $product) {
                $this->warn("No product {$data['sku']}");

                continue;
            }

            $inches = (float) $feet * 12;
            $this->line(sprintf('%-14s %6.0f ft = %7.0f"  (was %s%s)', $product->sku, $feet, $inches,
                $product->is_length_based ? 'length-based ' : 'each ', $product->configurator_length ? $product->configurator_length.'"' : '-'));

            if ($this->option('apply')) {
                $product->forceFill(['is_length_based' => true, 'configurator_length' => $inches])->save();
            }
            $changed++;
        }
        fclose($handle);

        $this->info(($this->option('apply') ? 'Applied' : 'Dry run —').(" {$changed} product(s); {$skipped} row(s) without a roll length skipped."));

        return self::SUCCESS;
    }
}
