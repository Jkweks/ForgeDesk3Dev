<?php

namespace App\Services\CutFlow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to the local tiger-bridge service (a separate small Node app, not
 * part of this repo), which is what actually owns the TigerStop's COM port
 * and the Zebra printer's socket. This app never touches the hardware
 * directly.
 */
class TigerBridgeClient
{
    protected string $baseUrl;

    protected string $token;

    protected bool $fake;

    public function __construct()
    {
        $this->baseUrl = config('services.tiger_bridge.url', 'http://127.0.0.1:9111');
        $this->token = (string) config('services.tiger_bridge.token', '');
        $this->fake = (bool) config('services.tiger_bridge.fake', false);

        if (! $this->fake && $this->token === '') {
            // Not fatal here — the bridge itself refuses to run without a
            // token, so a misconfigured deploy fails loudly there instead of
            // silently talking to an open bridge. This just surfaces it
            // sooner on the Laravel side.
            Log::warning('[tiger-bridge] TIGER_BRIDGE_TOKEN is not configured; bridge calls will be rejected.');
        }
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)->withToken($this->token);
    }

    public function move(float $inches): array
    {
        if ($this->fake) {
            Log::info('[tiger-bridge:fake] move', ['inches' => $inches]);

            return ['ok' => true, 'data' => ['started' => true, 'finished' => true, 'raw' => 'FAKE']];
        }

        try {
            $response = $this->client()->timeout(20)->post('/move', [
                'inches' => $inches,
            ]);

            return [
                'ok' => $response->successful(),
                'data' => $response->json(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function printLabel(array $payload): array
    {
        if ($this->fake) {
            Log::info('[tiger-bridge:fake] printLabel', $payload);

            return ['ok' => true, 'data' => ['fake' => true]];
        }

        try {
            $response = $this->client()->timeout(10)->post('/print', $payload);

            return [
                'ok' => $response->successful(),
                'data' => $response->json(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function status(): array
    {
        if ($this->fake) {
            return ['ok' => true, 'data' => [
                'status' => 'idle',
                'serialConnected' => true,
                'lastPosition' => null,
                'lastPositionAt' => null,
                'fake' => true,
            ]];
        }

        try {
            $response = $this->client()->timeout(5)->get('/status');

            return ['ok' => $response->successful(), 'data' => $response->json()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Polled while a cut is waiting on the physical cut-complete sensor
     * (see Dashboard::checkSensor()). Returns 'idle' | 'cutting' | 'complete'.
     * The sensor hardware itself isn't wired up yet, so this fails soft like
     * every other bridge call. In fake mode it reports 'complete' immediately
     * so the sensor-gated flow doesn't hang forever with no bridge to poll.
     */
    public function sensorStatus(): array
    {
        if ($this->fake) {
            return ['ok' => true, 'data' => ['status' => 'complete', 'fake' => true]];
        }

        try {
            $response = $this->client()->timeout(5)->get('/sensor/status');

            return ['ok' => $response->successful(), 'data' => $response->json()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
