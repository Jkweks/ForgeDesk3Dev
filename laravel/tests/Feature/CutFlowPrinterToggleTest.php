<?php

namespace Tests\Feature;

use App\Models\CutFlow\CutFlowSetting;
use App\Services\CutFlow\TigerBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutFlowPrinterToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('cutflow')->hasTable('cutflow_settings')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
        CutFlowSetting::current()->update(['cut_sensor_active' => false, 'show_cut_toast' => true, 'bridge_url' => 'http://bridge.test', 'bridge_token' => 't', 'bridge_fake' => false]);
        Http::fake(['bridge.test/*' => Http::response(['ok' => true], 200)]);
    }

    public function test_printer_defaults_on_and_prints(): void
    {
        $this->assertTrue(CutFlowSetting::current()->printerEnabled());

        $this->assertTrue(app(TigerBridgeClient::class)->printLabel(['size' => '12'])['ok']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/print'));
    }

    public function test_printer_off_sends_nothing_but_reports_ok(): void
    {
        CutFlowSetting::current()->update(['printer_enabled' => false]);

        $result = app(TigerBridgeClient::class)->printLabel(['size' => '12']);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['printerDisabled']);
        Http::assertNothingSent();
    }

    public function test_moves_still_reach_the_saw_with_printer_off(): void
    {
        CutFlowSetting::current()->update(['printer_enabled' => false]);

        app(TigerBridgeClient::class)->move(24.0);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/move'));
    }
}
