<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders the Project Management pages to /tmp so tests/js-smoke/smoke.js can run each page's own
 * script in jsdom against canned API data (catches runtime JS errors no PHP test can see).
 * Only runs when DUMP_PM_PAGES=1; see tests/js-smoke/README.md.
 */
class DumpPmPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump(): void
    {
        if (! getenv('DUMP_PM_PAGES')) {
            $this->markTestSkipped('Set DUMP_PM_PAGES=1 to dump the pages for the JS smoke test.');
        }
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        foreach (['/pm' => 'dashboard', '/pm/jobs' => 'jobs', '/pm/vendors' => 'vendors', '/search' => 'search', '/pm/inbox' => 'inbox', '/pm/jobs/1' => 'job', '/pm/purchase-orders' => 'pos', '/pm/purchase-orders/new' => 'po', '/pm/library' => 'library',
            '/pm/history' => 'history', '/pm/todos' => 'todos', '/pm/receiving' => 'receiving', '/fabrication/work-order-requests' => 'wor'] as $url => $name) {
            file_put_contents("/tmp/pm-page-$name.html", $this->get($url)->assertOk()->getContent());
        }
        $this->assertTrue(true);
    }
}
