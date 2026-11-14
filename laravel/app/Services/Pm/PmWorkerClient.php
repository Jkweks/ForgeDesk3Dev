<?php

namespace App\Services\Pm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** HTTP client for the pm-worker service (BidEstimate parser; later OCR / drawing import). */
class PmWorkerClient
{
    private function http()
    {
        $cfg = config('services.pm_worker');
        if (empty($cfg['token'])) {
            throw new RuntimeException('PM worker is not configured (PM_WORKER_TOKEN).');
        }

        return Http::baseUrl($cfg['url'])->timeout($cfg['timeout'])->withHeaders(['X-Worker-Token' => $cfg['token']]);
    }

    /** @return string[] sheet names in a BidEstimate workbook */
    public function estimateSheets(string $bytes): array
    {
        return $this->post('/estimate/sheets', $bytes)['sheets'] ?? [];
    }

    public function parseEstimate(string $bytes, string $sheet): array
    {
        return $this->post('/estimate/parse?sheet='.urlencode($sheet), $bytes);
    }

    private function post(string $path, string $bytes): array
    {
        $res = $this->http()->withBody($bytes, 'application/octet-stream')->post($path);
        if ($res->status() === 422) {
            throw new PmWorkerDataException($res->json('error') ?? 'The worker could not read this file.');
        }
        $res->throw();

        return $res->json();
    }
}
