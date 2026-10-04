<?php

namespace Tests\Unit;

use App\Services\CutFlow\TigerBridgeClient;
use PHPUnit\Framework\TestCase;

class TigerBridgeClientTest extends TestCase
{
    public function test_in_range_lengths_have_no_error(): void
    {
        $this->assertNull(TigerBridgeClient::rangeError(48.0, 6.0, 78.0));
        $this->assertNull(TigerBridgeClient::rangeError(6.0, 6.0, 78.0));
        $this->assertNull(TigerBridgeClient::rangeError(78.0, 6.0, 78.0));
    }

    public function test_below_minimum_and_beyond_maximum_are_reported(): void
    {
        $this->assertStringContainsString('below', TigerBridgeClient::rangeError(5.5, 6.0, 78.0));
        $this->assertStringContainsString('beyond', TigerBridgeClient::rangeError(96.0, 6.0, 78.0));
        $this->assertStringContainsString('(78")', TigerBridgeClient::rangeError(96.0, 6.0, 78.0));
    }

    public function test_float_noise_at_the_limit_is_tolerated(): void
    {
        $this->assertNull(TigerBridgeClient::rangeError(78.0004, 6.0, 78.0));
        $this->assertNull(TigerBridgeClient::rangeError(5.9996, 6.0, 78.0));
    }

    public function test_unknown_limits_skip_the_check(): void
    {
        $this->assertNull(TigerBridgeClient::rangeError(500.0, null, null));
        $this->assertNull(TigerBridgeClient::rangeError(0.5, null, 78.0));
    }

    public function test_outdated_bridge_detection(): void
    {
        $this->assertTrue(TigerBridgeClient::isOutdated(null));
        $this->assertTrue(TigerBridgeClient::isOutdated('0.1.0'));
        $this->assertFalse(TigerBridgeClient::isOutdated(TigerBridgeClient::MIN_BRIDGE_VERSION));
        $this->assertFalse(TigerBridgeClient::isOutdated('9.0.0'));
    }
}
