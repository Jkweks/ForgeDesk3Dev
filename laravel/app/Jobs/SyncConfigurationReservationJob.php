<?php

namespace App\Jobs;

use App\Models\DoorFrameConfiguration;
use App\Models\User;
use App\Services\Configurator\ConfigurationReservationBridge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a "reserved" configuration's current BOM into its linked
 * JobReservation off the request path. Previously ran synchronously on
 * every part edit/generate call — ConfigurationReservationBridge::reserve()
 * reloads the whole BOM tree and mutates JobReservationItem rows one at a
 * time, each triggering a Product save + committed-quantity recalculation
 * (bin-packing over every active reservation item for that product), so a
 * single field edit could cascade into N product saves and N bin-packing
 * scans. Queuing it keeps that cost off the user's save request; the
 * explicit Reserve/Release actions still call the bridge directly and wait
 * for the result, since those need to report warnings back immediately.
 */
class SyncConfigurationReservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $configurationId,
        private readonly ?int $userId,
    ) {}

    public function handle(ConfigurationReservationBridge $reservationBridge): void
    {
        $config = DoorFrameConfiguration::find($this->configurationId);
        if (! $config || $config->status !== 'reserved') {
            return;
        }

        $user = $this->userId ? User::find($this->userId) : null;

        try {
            $reservationBridge->reserve($config, $user);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync reservation after configuration edit', [
                'config_id' => $this->configurationId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
