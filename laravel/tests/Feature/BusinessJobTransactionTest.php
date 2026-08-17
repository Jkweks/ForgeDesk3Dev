<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression coverage for BusinessJobController::createTransaction() previously
 * falling back to a direct quantity_on_hand write when a product had no
 * inventory_locations row. It now creates an "Unassigned" location and routes
 * through recalculateQuantitiesFromLocations() either way.
 */
class BusinessJobTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_unassigned_location_when_product_has_none(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $job = BusinessJob::create([
            'job_number' => 'BJ-1',
            'job_name' => 'Test Business Job',
            'status' => 'active',
        ]);

        $product = Product::create([
            'sku' => 'BJ-TEST-001',
            'description' => 'Business job test product',
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/v1/business-jobs/{$job->id}/transactions", [
            'product_id' => $product->id,
            'type' => 'receipt',
            'quantity' => 8,
        ]);

        $response->assertCreated();

        $product->refresh();
        $this->assertEquals(8, $product->quantity_on_hand);
        $this->assertDatabaseHas('inventory_locations', [
            'product_id' => $product->id,
            'quantity' => 8,
            'is_primary' => true,
        ]);
    }

    public function test_updates_existing_location_when_present(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $job = BusinessJob::create([
            'job_number' => 'BJ-2',
            'job_name' => 'Test Business Job 2',
            'status' => 'active',
        ]);

        $product = Product::create([
            'sku' => 'BJ-TEST-002',
            'description' => 'Business job test product 2',
        ]);

        $storageLocation = \App\Models\StorageLocation::create([
            'name' => 'Bin C3',
            'code' => 'BIN-C3',
        ]);

        InventoryLocation::create([
            'product_id' => $product->id,
            'storage_location_id' => $storageLocation->id,
            'quantity' => 10,
            'is_primary' => true,
        ]);
        $product->recalculateQuantitiesFromLocations();

        Sanctum::actingAs($user, ['*']);

        // job_issue reduces stock
        $response = $this->postJson("/api/v1/business-jobs/{$job->id}/transactions", [
            'product_id' => $product->id,
            'type' => 'job_issue',
            'quantity' => 3,
        ]);

        $response->assertCreated();

        $product->refresh();
        $this->assertEquals(7, $product->quantity_on_hand);

        $location = InventoryLocation::where('product_id', $product->id)->first();
        $this->assertEquals(7, $location->quantity);
    }
}
