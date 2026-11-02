<?php

namespace Tests\Feature;

use App\Livewire\CutFlow\Settings;
use App\Services\CutFlow\BridgePackager;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class CutFlowBridgeDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        // CutFlow tables live on their own connection, which RefreshDatabase does not migrate.
        if (! Schema::connection('cutflow')->hasTable('cutflow_settings')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
        $this->src = sys_get_temp_dir().'/bridge-src-'.uniqid();
        \App\Models\CutFlow\CutFlowSetting::current()->update(['cut_sensor_active' => false, 'show_cut_toast' => true]);
        File::makeDirectory($this->src.'/node_modules/x', 0777, true);
        File::makeDirectory($this->src.'/logs');
        File::makeDirectory($this->src.'/service');
        File::put($this->src.'/package.json', '{}');
        File::put($this->src.'/server.js', '//');
        File::put($this->src.'/service/run.ps1', '#');
        File::put($this->src.'/.env', 'BRIDGE_TOKEN=live-secret');
        File::put($this->src.'/.env.example', "PORT=1\nBRIDGE_TOKEN=public-example-token\nX=2\n");
        File::put($this->src.'/node_modules/x/i.js', '//');
        File::put($this->src.'/logs/a.log', 'log');
        config(['services.tiger_bridge.source_dir' => $this->src]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->src);
        parent::tearDown();
    }

    public function test_package_has_source_only_and_a_blank_token(): void
    {
        $path = BridgePackager::build();
        $zip = new ZipArchive;
        $zip->open($path);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $this->assertEqualsCanonicalizing(
            ['tiger-bridge/package.json', 'tiger-bridge/server.js', 'tiger-bridge/service/run.ps1', 'tiger-bridge/.env.example'],
            $names
        );
        $example = $zip->getFromName('tiger-bridge/.env.example');
        $this->assertStringContainsString("BRIDGE_TOKEN=\n", $example);
        $this->assertStringNotContainsString('public-example-token', $example);
        $zip->close();
        unlink($path);
    }

    public function test_locked_settings_page_cannot_download(): void
    {
        Livewire::test(Settings::class)
            ->call('downloadBridge')
            ->assertNoFileDownloaded();
    }

    public function test_non_admin_pin_cannot_download(): void
    {
        \App\Models\FdUser::create(['name' => 'Worker', 'role' => 'worker', 'active' => true, 'fab_pin' => \Illuminate\Support\Facades\Hash::make('1111')]);

        Livewire::test(Settings::class)->set('pin', '1111')->call('unlock')->call('downloadBridge')->assertNoFileDownloaded();
    }

    public function test_unlocked_admin_downloads_zip(): void
    {
        \App\Models\FdUser::create(['name' => 'Boss', 'role' => 'admin', 'active' => true, 'fab_pin' => \Illuminate\Support\Facades\Hash::make('4321')]);

        Livewire::test(Settings::class)
            ->set('pin', '4321')
            ->call('unlock')
            ->call('downloadBridge')
            ->assertFileDownloaded('tiger-bridge.zip');
    }
}
