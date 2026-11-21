<?php

namespace Tests\Feature;

use App\Livewire\CutFlow\Dashboard;
use App\Models\CutFlow\CutLogEntry;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CutStationDropLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }

        config(['services.tiger_bridge.fake' => true]);

        // The migration that allows type 'drop' only widens the CHECK on Postgres; SQLite keeps the old one.
        DB::connection('cutflow')->statement('PRAGMA ignore_check_constraints = ON');
    }

    private function product(string $finish, bool $drop = true): Product
    {
        return Product::create([
            'sku' => 'E14025-'.$finish,
            'description' => 'Test profile',
            'supplier_id' => \App\Models\Supplier::firstOrCreate(['name' => 'Test Supplier'])->id,
            'part_number' => 'E14025',
            'finish' => $finish,
            'drop_rack_enabled' => $drop,
        ]);
    }

    private function managerDashboard(string $role = 'manager')
    {
        $pin = (string) (1000 + crc32($role) % 9000);
        \App\Models\FdUser::create(['name' => ucfirst($role), 'role' => $role, 'active' => true, 'fab_pin' => \Illuminate\Support\Facades\Hash::make($pin)]);

        return Livewire::test(Dashboard::class)->call('openLogin')->set('keypadValue', $pin)->call('confirmModal');
    }

    public function test_workers_and_signed_out_users_cannot_open_or_print(): void
    {
        $this->product('C2');

        foreach (['worker', null] as $role) {
            $c = $role ? $this->managerDashboard($role) : Livewire::test(Dashboard::class);
            $c->call('openDropLabel')->assertNotSet('modal', 'droplabel')->assertDontSee('Drop Label');
            $c->set('modal', 'droplabel')->set('dropPart', 'E14025')->set('dropLength', '83')->call('printManualDropLabel');
        }

        $this->assertSame(0, CutLogEntry::count());
        $this->managerDashboard('admin')->assertSee('Drop Label');
    }

    public function test_only_drop_rack_finishes_are_offered(): void
    {
        $this->product('C2');
        $this->product('BL');
        $this->product('DB', drop: false);

        $component = $this->managerDashboard()->call('openDropLabel')->set('dropPart', 'e14025');

        $this->assertEqualsCanonicalizing(['C2', 'BL'], $component->instance()->dropFinishOptions()->keys()->all());
    }

    public function test_prints_a_floored_drop_tag_and_logs_it(): void
    {
        $this->product('C2');
        Log::spy();

        $this->managerDashboard()
            ->call('openDropLabel')
            ->set('dropPart', 'E14025')
            ->assertSet('dropFinish', 'C2') // single option auto-selects
            ->set('dropLength', '83')
            ->call('printManualDropLabel')
            ->assertSet('modal', null)
            ->assertSet('dropError', '');

        $entry = CutLogEntry::latest('id')->first();
        $this->assertSame('drop', $entry->type);
        $this->assertSame('E14025', $entry->part_name);
        $this->assertSame('C2', $entry->finish);
        $this->assertEqualsWithDelta(83.0, (float) $entry->dimension_inches, 0.001);

        Log::shouldHaveReceived('info')->withArgs(fn ($msg, $payload = []) => str_contains($msg, 'printLabel')
            && ($payload['sku'] ?? null) === 'E14025-C2' && ($payload['size'] ?? null) === '80');
    }

    public function test_rejects_non_drop_rack_part_and_bad_input(): void
    {
        $this->product('C2', drop: false);

        $this->managerDashboard()
            ->call('openDropLabel')
            ->set('dropPart', 'E14025')
            ->set('dropLength', '40')
            ->call('printManualDropLabel')
            ->assertSet('modal', 'droplabel')
            ->assertNotSet('dropError', '');

        $this->assertSame(0, CutLogEntry::count());
    }

    public function test_too_short_to_rack_is_rejected(): void
    {
        $this->product('C2');

        $this->managerDashboard()
            ->call('openDropLabel')
            ->set('dropPart', 'E14025')
            ->set('dropLength', '4')
            ->call('printManualDropLabel')
            ->assertNotSet('dropError', '');

        $this->assertSame(0, CutLogEntry::count());
    }
}
