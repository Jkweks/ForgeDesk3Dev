<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFinishVariantsTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->supplier = Supplier::create(['name' => 'Variant Supplier']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'part_number' => 'A646060', 'finish' => 'C2', 'description' => 'Test extrusion',
            'long_description' => 'Longer text', 'unit_cost' => 12.5, 'net_cost' => 9.75,
            'supplier_id' => $this->supplier->id, 'supplier_sku' => 'SUP-1', 'lead_time_days' => 14,
            'quantity_on_hand' => 40, 'on_order_qty' => 10, 'location' => 'Rack A',
            'minimum_quantity' => 5, 'reorder_point' => 8, 'safety_stock' => 2, 'unit_of_measure' => 'EA',
            'pack_size' => 1, 'nonsof' => true, 'cp_part' => true, 'is_shared' => true, 'is_special_order' => false,
        ];
    }

    public function test_extra_finishes_are_created_with_the_same_details_and_their_own_skus(): void
    {
        $category = Category::create(['name' => 'Extrusions']);

        $response = $this->postJson('/api/v1/products', $this->payload(['finish_variants' => ['DB', 'BL'], 'category_ids' => [$category->id]]))
            ->assertCreated();

        $this->assertSame('A646060-C2', $response->json('sku'));
        $this->assertSame(['A646060-DB', 'A646060-BL'], array_column($response->json('variants'), 'sku'));

        $products = Product::where('part_number', 'A646060')->get()->keyBy('finish');
        $this->assertEqualsCanonicalizing(['C2', 'DB', 'BL'], $products->keys()->all());

        foreach (['DB', 'BL'] as $finish) {
            $variant = $products[$finish];
            $primary = $products['C2'];
            foreach (['description', 'long_description', 'unit_cost', 'net_cost', 'supplier_id', 'supplier_sku', 'lead_time_days',
                'minimum_quantity', 'reorder_point', 'safety_stock', 'unit_of_measure', 'pack_size', 'nonsof', 'cp_part', 'is_shared', 'is_special_order'] as $field) {
                $this->assertEquals($primary->$field, $variant->$field, "{$field} should be copied to the {$finish} variant");
            }
            $this->assertSame([$category->id], $variant->categories()->pluck('categories.id')->all(), 'categories are copied');
        }
    }

    public function test_variants_start_without_stock_unless_asked_to_copy_it(): void
    {
        $this->postJson('/api/v1/products', $this->payload(['finish_variants' => ['DB']]))->assertCreated();
        $primary = Product::where('sku', 'A646060-C2')->first();
        $variant = Product::where('sku', 'A646060-DB')->first();
        $this->assertSame([40, 10], [(int) $primary->quantity_on_hand, (int) $primary->on_order_qty]);
        $this->assertSame([0, 0], [(int) $variant->quantity_on_hand, (int) $variant->on_order_qty], 'each finish is separate stock');

        $this->postJson('/api/v1/products', $this->payload(['part_number' => 'B1', 'finish_variants' => ['DB'], 'copy_stock_to_variants' => true]))->assertCreated();
        $copied = Product::where('sku', 'B1-DB')->first();
        $this->assertSame([40, 10], [(int) $copied->quantity_on_hand, (int) $copied->on_order_qty]);
    }

    public function test_custom_skus_get_their_variant_suffix_swapped_or_appended(): void
    {
        $this->postJson('/api/v1/products', $this->payload(['part_number' => null, 'sku' => 'CUSTOM-C2', 'finish_variants' => ['DB']]))->assertCreated();
        $this->assertNotNull(Product::where('sku', 'CUSTOM-DB')->first(), 'a trailing -C2 is swapped');

        $this->postJson('/api/v1/products', $this->payload(['part_number' => null, 'sku' => 'WIDGET1', 'finish' => null, 'finish_variants' => ['BL']]))->assertCreated();
        $this->assertNotNull(Product::where('sku', 'WIDGET1-BL')->first(), 'no finish suffix: -BL is appended');
    }

    public function test_a_clashing_variant_sku_creates_nothing(): void
    {
        Product::create(['sku' => 'A646060-DB', 'description' => 'Existing', 'supplier_id' => $this->supplier->id]);

        $this->postJson('/api/v1/products', $this->payload(['finish_variants' => ['DB', 'BL']]))
            ->assertStatus(422)
            ->assertJsonPath('errors.finish_variants.0', fn ($m) => str_contains($m, 'A646060-DB') && str_contains($m, 'no products were created'));

        $this->assertNull(Product::where('sku', 'A646060-C2')->first(), 'the primary is not created either');
        $this->assertNull(Product::where('sku', 'A646060-BL')->first());
    }

    public function test_variant_input_is_validated(): void
    {
        $this->postJson('/api/v1/products', $this->payload(['finish_variants' => ['ZZ']]))->assertStatus(422)->assertJsonValidationErrors('finish_variants.0');

        $this->postJson('/api/v1/products', $this->payload(['part_number' => null, 'finish_variants' => ['DB']]))
            ->assertStatus(422)->assertJsonPath('errors.finish_variants.0', fn ($m) => str_contains($m, 'part number or SKU'));

        // The primary finish and duplicates in the list are ignored rather than making clashing SKUs.
        $this->postJson('/api/v1/products', $this->payload(['finish_variants' => ['C2', 'DB', 'DB']]))->assertCreated()
            ->assertJsonCount(1, 'variants');
    }

    public function test_without_variants_the_response_and_behavior_are_unchanged_and_toggles_are_now_saved(): void
    {
        $response = $this->postJson('/api/v1/products', $this->payload())->assertCreated();

        $this->assertArrayNotHasKey('variants', $response->json());
        $product = Product::where('sku', 'A646060-C2')->first();
        $this->assertTrue($product->nonsof && $product->cp_part && $product->is_shared, 'the add form toggles used to be silently dropped on create');
    }

    public function test_the_add_product_modal_offers_variants_fits_better_and_scrolls_with_a_visible_bar(): void
    {
        $html = $this->get('/inventory/products')->assertOk()->getContent();

        foreach (['id="finishVariantsRow"', 'id="finishVariantOptions"', 'id="copyStockToVariants"', 'id="variantPreview"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        // Compact layout: Pricing, Supplier and Initial Stock share a row; Stock Management sits beside Unit of Measure.
        $this->assertStringContainsString('<div class="col-lg-3">', $html);
        $this->assertStringContainsString('<div class="col-lg-5">', $html);
        $this->assertStringContainsString('<div class="col-lg-8">', $html);
        $this->assertStringContainsString('finish_variants = variantFinishes', $html);

        // Regression guard: a <form> between .modal-content and .modal-body must still let the body scroll.
        $this->assertStringContainsString('.modal-content > form { display: flex; flex-direction: column;', $html);
        $this->assertStringContainsString('.modal-content > form > .modal-body { flex: 1 1 auto; min-height: 0; }', $html);
        $this->assertStringContainsString('scrollbar-color: color-mix(', $html);
    }
}
