<?php

namespace App\Console\Commands;

use App\Models\InventoryLocation;
use App\Models\InventoryTransaction;
use App\Models\JobReservation;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairFulfilledLocationDeductions extends Command
{
    protected $signature = 'inventory:repair-fulfilled-deductions
                            {--dry-run : Show what would be changed without modifying data}
                            {--last=0 : Only process the N most recently fulfilled reservations}';

    protected $description = 'Retroactively deduct inventory from storage locations for fulfilled jobs that were completed before the location-deduction fix';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $last = (int) $this->option('last');

        if ($dryRun) {
            $this->info('=== DRY RUN MODE - No changes will be made ===');
        }

        $this->info('Scanning fulfilled reservations for missing location deductions...');

        $query = JobReservation::where('status', 'fulfilled')
            ->with(['items.product'])
            ->orderBy('updated_at', 'desc');

        if ($last > 0) {
            $query->limit($last);
            $this->info("Limiting to the {$last} most recently fulfilled reservation(s).");
        }

        $reservations = $query->get();

        $this->info("Found {$reservations->count()} fulfilled reservation(s) to process.");

        $totalDeductions = 0;
        $productsAffected = [];

        foreach ($reservations as $reservation) {
            $refNumber = $reservation->job_number . '-R' . $reservation->release_number;

            foreach ($reservation->items as $item) {
                if ($item->consumed_qty <= 0) {
                    continue;
                }

                $product = $item->product;

                // Check if a fulfillment transaction already exists for this job+product
                $existingTransaction = InventoryTransaction::where('product_id', $item->product_id)
                    ->where('type', 'fulfillment')
                    ->where('reference_number', $refNumber)
                    ->exists();

                if ($existingTransaction) {
                    $this->line("  SKIP {$product->sku} on {$refNumber} — already has fulfillment transaction");
                    continue;
                }

                // This item was consumed without location deductions
                $this->info("  REPAIR {$product->sku} on {$refNumber}: consumed {$item->consumed_qty}");

                if ($dryRun) {
                    $this->showDeductionPlan($item->product_id, $item->consumed_qty);
                    $totalDeductions++;
                    $productsAffected[$item->product_id] = $product->sku;
                    continue;
                }

                DB::beginTransaction();
                try {
                    $stockBefore = $product->quantity_on_hand;
                    $remaining = $item->consumed_qty;

                    // Get locations: primary first, then secondary by id
                    $locations = InventoryLocation::where('product_id', $item->product_id)
                        ->orderBy('is_primary', 'desc')
                        ->orderBy('id', 'asc')
                        ->get();

                    foreach ($locations as $location) {
                        if ($remaining <= 0) {
                            break;
                        }

                        $deduct = min($remaining, $location->quantity);
                        if ($deduct > 0) {
                            $location->quantity -= $deduct;
                            $location->save();
                            $remaining -= $deduct;

                            $locationName = $location->storageLocation->full_path ?? "Location #{$location->storage_location_id}";
                            $this->line("    Deducted {$deduct} from {$locationName} (now {$location->quantity})");
                        }
                    }

                    // Over-consumption remainder goes to primary (allows negative)
                    if ($remaining > 0) {
                        $primaryLocation = $locations->where('is_primary', true)->first();
                        $target = $primaryLocation ?? $locations->first();

                        if ($target) {
                            $target->quantity -= $remaining;
                            $target->save();

                            $locationName = $target->storageLocation->full_path ?? "Location #{$target->storage_location_id}";
                            $this->warn("    Over-consumption: deducted remaining {$remaining} from {$locationName} (now {$target->quantity})");
                        }
                    }

                    // Recalculate product totals from locations
                    $product->recalculateQuantitiesFromLocations();

                    // Create audit transaction
                    InventoryTransaction::create([
                        'product_id' => $item->product_id,
                        'type' => 'fulfillment',
                        'quantity' => -$item->consumed_qty,
                        'quantity_before' => $stockBefore,
                        'quantity_after' => $product->quantity_on_hand,
                        'reference_number' => $refNumber,
                        'notes' => "Retroactive location deduction repair for job {$reservation->job_number} R{$reservation->release_number}",
                        'transaction_date' => $reservation->updated_at,
                    ]);

                    DB::commit();

                    $totalDeductions++;
                    $productsAffected[$item->product_id] = $product->sku;

                    $this->info("    Product on_hand: {$stockBefore} -> {$product->quantity_on_hand}");

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("    FAILED for {$product->sku}: {$e->getMessage()}");
                    Log::error('Repair deduction failed', [
                        'product_id' => $item->product_id,
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("=== DRY RUN COMPLETE ===");
            $this->info("{$totalDeductions} deduction(s) would be applied across " . count($productsAffected) . " product(s).");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("Repair complete: {$totalDeductions} deduction(s) applied across " . count($productsAffected) . " product(s).");
        }

        return 0;
    }

    private function showDeductionPlan(int $productId, int $quantity): void
    {
        $locations = InventoryLocation::where('product_id', $productId)
            ->with('storageLocation')
            ->orderBy('is_primary', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $remaining = $quantity;

        foreach ($locations as $location) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min($remaining, $location->quantity);
            if ($deduct > 0) {
                $locationName = $location->storageLocation->full_path ?? "Location #{$location->storage_location_id}";
                $primary = $location->is_primary ? ' [PRIMARY]' : '';
                $this->line("    Would deduct {$deduct} from {$locationName}{$primary} ({$location->quantity} -> " . ($location->quantity - $deduct) . ")");
                $remaining -= $deduct;
            }
        }

        if ($remaining > 0) {
            $primaryLocation = $locations->where('is_primary', true)->first();
            $target = $primaryLocation ?? $locations->first();
            if ($target) {
                $locationName = $target->storageLocation->full_path ?? "Location #{$target->storage_location_id}";
                $this->warn("    Would over-deduct remaining {$remaining} from {$locationName} (would become " . ($target->quantity - $remaining) . ")");
            }
        }
    }
}
