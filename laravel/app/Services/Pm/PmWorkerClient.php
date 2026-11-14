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

    /** Read a shop-drawing set PDF. Slow (about a second a page), so it gets a long timeout; run it from a queue job. */
    public function parseDrawingSet(string $pdfBytes): array
    {
        $res = $this->http()->timeout(900)->withBody($pdfBytes, 'application/pdf')->post('/drawing/parse');
        $res->throw();

        return $res->json();
    }

    /** One page of a PDF as PNG bytes (1-based page), for the size-check viewer. */
    public function pageImage(string $pdfBytes, int $page, int $dpi = 110): string
    {
        $res = $this->http()->timeout(120)->withBody($pdfBytes, 'application/pdf')->post("/pdf/page-image?page={$page}&dpi={$dpi}");
        if ($res->status() === 422) {
            throw new PmWorkerDataException($res->json('error') ?? 'The page could not be rendered.');
        }
        $res->throw();

        return $res->body();
    }

    /** Merge PDFs (given as raw bytes, in order) into one. @param string[] $pdfs */
    public function mergePdfs(array $pdfs): string
    {
        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'pmmerge');
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach (array_values($pdfs) as $i => $bytes) {
            $zip->addFromString(sprintf('%04d.pdf', $i), $bytes);
        }
        $zip->close();
        $body = file_get_contents($tmp);
        unlink($tmp);

        $res = $this->http()->withBody($body, 'application/zip')->post('/pdf/merge');
        if ($res->status() === 422) {
            throw new PmWorkerDataException($res->json('error') ?? 'The PDFs could not be merged.');
        }
        $res->throw();

        return $res->body();
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
