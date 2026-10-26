<?php

namespace Tests\Feature;

use App\Mail\SpecialOrdersReceivedMail;
use App\Models\BusinessJob;
use App\Models\JobReservation;
use App\Models\JobReservationItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialOrderReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->watcher = User::factory()->create(['role' => 'office_staff', 'is_active' => true, 'email' => 'watcher@example.test']);
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function product(string $sku, bool $special, float $onHand = 0): Product
    {
        return Product::create([
            'sku' => $sku, 'description' => $sku, 'quantity_on_hand' => $onHand, 'is_special_order' => $special,
            'supplier_id' => Supplier::firstOrCreate(['name' => 'S'])->id,
        ]);
    }

    private function reservation(array $items, bool $notify = true): JobReservation
    {
        $job = BusinessJob::firstOrCreate(['job_number' => 'J-1'], ['job_name' => 'Tower', 'status' => 'active']);
        $r = JobReservation::create(['business_job_id' => $job->id, 'job_number' => 'J-1', 'job_name' => 'Tower', 'requested_by' => 'x', 'status' => 'active']);
        foreach ($items as [$product, $qty]) {
            JobReservationItem::create(['reservation_id' => $r->id, 'product_id' => $product->id, 'requested_qty' => $qty, 'committed_qty' => $qty, 'consumed_qty' => 0]);
        }
        if ($notify) {
            $r->applyNotifySettings(true, [$this->watcher->id]);
        }

        return $r->fresh();
    }

    private function receive(Product $product, int $qty): void
    {
        $po = PurchaseOrder::create(['po_number' => 'PO-'.$product->id.$qty, 'status' => 'approved', 'order_date' => now()->toDateString()]);
        $item = PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'product_id' => $product->id, 'quantity_ordered' => $qty, 'unit_cost' => 1, 'total_cost' => $qty]);
        $loc = StorageLocation::firstOrCreate(['code' => 'B1'], ['name' => 'B1']);
        $this->postJson("/api/v1/purchase-orders/{$po->id}/receive", ['items' => [['item_id' => $item->id, 'quantity' => $qty, 'storage_location_id' => $loc->id]]])->assertOk();
    }

    public function test_notifies_only_once_all_special_order_items_are_received(): void
    {
        $a = $this->product('SPEC-A', true);
        $b = $this->product('SPEC-B', true);
        $stock = $this->product('STOCK-1', false, 100);
        $r = $this->reservation([[$a, 4], [$b, 2], [$stock, 10]]);

        $this->receive($a, 4);

        Mail::assertNothingSent();
        $this->assertNull($r->fresh()->special_received_notified_at);

        $this->receive($b, 2);
        Mail::assertQueued(SpecialOrdersReceivedMail::class, fn ($m) => $m->hasTo('watcher@example.test'));
        $this->assertNotNull($r->fresh()->special_received_notified_at);
        $this->assertSame(1, SystemNotification::where('type', 'special_orders_received')->count());
    }

    public function test_partial_receipt_of_a_line_does_not_notify(): void
    {
        $a = $this->product('SPEC-A', true);
        $this->reservation([[$a, 10]]);

        $this->receive($a, 6);

        Mail::assertNothingSent();
    }

    public function test_sends_only_once_and_not_without_the_option(): void
    {
        $a = $this->product('SPEC-A', true);
        $off = $this->reservation([[$a, 3]], false);
        $on = $this->reservation([[$a, 3]]);

        $this->receive($a, 20);
        $this->artisan('reservations:check-special-orders')->assertSuccessful();
        $this->artisan('reservations:check-special-orders')->assertSuccessful();

        Mail::assertQueued(SpecialOrdersReceivedMail::class, 1);
        $this->assertNull($off->fresh()->special_received_notified_at);
        $this->assertNotNull($on->fresh()->special_received_notified_at);
    }

    public function test_reservation_without_special_order_items_never_notifies(): void
    {
        $stock = $this->product('STOCK-1', false, 100);
        $r = $this->reservation([[$stock, 5]]);

        $this->artisan('reservations:check-special-orders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($r->fresh()->special_received_notified_at);
    }

    public function test_stock_shared_with_another_reservation_does_not_count_twice(): void
    {
        $a = $this->product('SPEC-A', true, 5);
        $this->reservation([[$a, 5]], false);          // another job already holds all 5
        $this->reservation([[$a, 5]]);                  // watched job needs 5 more

        $this->artisan('reservations:check-special-orders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_cancelled_reservation_is_ignored(): void
    {
        $a = $this->product('SPEC-A', true, 50);
        $r = $this->reservation([[$a, 5]]);
        $r->forceFill(['status' => 'cancelled'])->save();

        $this->artisan('reservations:check-special-orders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_adding_a_new_special_item_rearms_the_notice(): void
    {
        $a = $this->product('SPEC-A', true, 50);
        $b = $this->product('SPEC-B', true);
        $r = $this->reservation([[$a, 5]]);
        $this->artisan('reservations:check-special-orders')->assertSuccessful();
        $this->assertNotNull($r->fresh()->special_received_notified_at);

        $this->postJson("/api/v1/job-reservations/{$r->id}/items", ['product_id' => $b->id, 'requested_qty' => 2, 'committed_qty' => 2])->assertCreated();

        $this->assertNull($r->fresh()->special_received_notified_at);
    }

    public function test_option_can_be_set_through_the_api_and_requires_a_recipient(): void
    {
        $a = $this->product('SPEC-A', true);
        $r = $this->reservation([[$a, 5]], false);

        $this->putJson("/api/v1/job-reservations/{$r->id}", ['notify_special_received' => true, 'notify_user_ids' => []])->assertStatus(422);

        $this->putJson("/api/v1/job-reservations/{$r->id}", ['notify_special_received' => true, 'notify_user_ids' => [$this->watcher->id]])->assertOk();
        $show = $this->getJson("/api/v1/job-reservations/{$r->id}")->assertOk();
        $show->assertJsonPath('reservation.notify_special_received', true)->assertJsonPath('reservation.notify_user_ids.0', $this->watcher->id);
    }

    public function test_saving_unchanged_settings_does_not_resend(): void
    {
        $a = $this->product('SPEC-A', true, 50);
        $r = $this->reservation([[$a, 5]]);
        $this->artisan('reservations:check-special-orders')->assertSuccessful();

        $this->putJson("/api/v1/job-reservations/{$r->id}", ['job_name' => 'Renamed', 'notify_special_received' => true, 'notify_user_ids' => [$this->watcher->id]])->assertOk();

        Mail::assertQueued(SpecialOrdersReceivedMail::class, 1);
    }

    public function test_manual_create_requires_recipients_when_the_option_is_on(): void
    {
        // (The success path can't run on the sqlite test schema, where job_reservations.reservation_id
        // is NOT NULL for reservations created without a business job; validation runs first.)
        $a = $this->product('SPEC-A', true);

        $this->postJson('/api/v1/job-reservations/create-manual', [
            'job_number' => 'J-10', 'job_name' => 'Annex 2', 'requested_by_id' => $this->admin->id,
            'notify_special_received' => true, 'items' => [['product_id' => $a->id, 'requested_qty' => 3]],
        ])->assertStatus(422);
    }
}
