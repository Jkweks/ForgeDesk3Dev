<?php

namespace Tests\Unit;

use App\Support\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('s_items', function ($t) {
            $t->id();
            $t->string('sku');
            $t->string('description')->nullable();
        });
        DB::table('s_items')->insert([
            ['sku' => 'AB123BLK', 'description' => 'Black Aluminum Frame'],
            ['sku' => 'CD-9', 'description' => 'Door 100% steel'],
            ['sku' => 'ZZ1', 'description' => null],
        ]);
    }

    private function find(string $term): array
    {
        return Search::apply(DB::table('s_items'), $term, ['sku', 'description'])->orderBy('id')->pluck('sku')->all();
    }

    public function test_case_insensitive(): void
    {
        $this->assertSame(['AB123BLK'], $this->find('ab123blk'));
        $this->assertSame(['AB123BLK'], $this->find('ALUMINUM'));
    }

    public function test_tokens_any_order(): void
    {
        $this->assertSame(['AB123BLK'], $this->find('frame black'));
    }

    public function test_separator_insensitive(): void
    {
        $this->assertSame(['AB123BLK'], $this->find('ab-123 blk'));
        $this->assertSame(['CD-9'], $this->find('cd9'));
    }

    public function test_wildcards_are_literal(): void
    {
        $this->assertSame(['CD-9'], $this->find('100%'));
        $this->assertSame([], $this->find('%%%x'));
    }

    public function test_blank_returns_everything(): void
    {
        $this->assertCount(3, $this->find('   '));
    }

    public function test_in_memory_matches(): void
    {
        $this->assertTrue(Search::matches('ab-123 blk', 'AB123BLK'));
        $this->assertTrue(Search::matches('frame black', ['Black', 'Frame']));
        $this->assertTrue(Search::matches('/^3260\d+-WO1/', '3260901-WO1'));
        $this->assertTrue(Search::matches('alminum', 'Aluminum frame'));
        $this->assertFalse(Search::matches('zzzz', 'abc'));
        $this->assertTrue(Search::matches('', 'abc'));
    }
}
