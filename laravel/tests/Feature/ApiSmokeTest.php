<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Broad safety net: every parameterless GET endpoint under /api/v1 must answer an admin without a server
 * error on an empty database. Catches the class of bug where a controller trips over an unset optional input.
 */
class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Endpoints that legitimately need input or an external service (path => reason). */
    private const SKIP = [
        'api/v1/export/template' => 'streams a file',
        'api/v1/export/products' => 'streams a file',
        'api/v1/transactions-export' => 'streams a file',
    ];

    public function test_get_endpoints_do_not_error_on_an_empty_database(): void
    {
        // CutFlow tables live on their own connection, which RefreshDatabase does not migrate.
        if (! Schema::connection('cutflow')->hasTable('cut_log_entries')) {
            $this->artisan('migrate', ['--database' => 'cutflow', '--path' => 'database/migrations/cutflow', '--force' => true]);
        }
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);

        $failures = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || ! str_starts_with($uri, 'api/v1/') || str_contains($uri, '{') || isset(self::SKIP[$uri])) {
                continue;
            }
            $checked++;
            $status = $this->getJson('/'.$uri)->getStatusCode();
            if ($status >= 500) {
                $failures[] = "{$status} GET /{$uri}";
            }
        }

        $this->assertGreaterThan(50, $checked, 'the smoke test should be covering a meaningful number of routes');
        $this->assertSame([], $failures, "Endpoints returning server errors:\n".implode("\n", $failures));
    }
}
