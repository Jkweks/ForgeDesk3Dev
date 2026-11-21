<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Submits a PDF to a CUPS queue with a minimal IPP/1.1 Print-Job request over HTTP — no
 * cups-client binary needed in the container. Configured by services.label_printer.
 */
class LabelPrinterClient
{
    public function printPdf(string $pdf, string $jobName, int $copies = 1): array
    {
        $cfg = config('services.label_printer');

        if ($cfg['fake']) {
            Log::info('LabelPrinter (fake): would print', ['job' => $jobName, 'bytes' => strlen($pdf)]);

            return ['job_id' => null, 'fake' => true];
        }

        $httpUrl = $cfg['url'];
        $printerUri = preg_replace('#^https?://#', 'ipp://', $httpUrl);
        $parts = parse_url($httpUrl);
        if (empty($parts['port'])) {
            $printerUri = preg_replace('#^(ipp://[^/]+)#', '$1:631', $printerUri);
        }

        $body = $this->buildPrintJob($printerUri, $jobName, $cfg['media'], max(1, $copies)).$pdf;

        $res = Http::withBody($body, 'application/ipp')->timeout(30)->post($httpUrl);
        if (! $res->successful()) {
            throw new RuntimeException('Label printer HTTP '.$res->status());
        }

        $raw = $res->body();
        if (strlen($raw) < 8) {
            throw new RuntimeException('Label printer returned an invalid IPP response');
        }
        $status = unpack('n', substr($raw, 2, 2))[1];
        if ($status >= 0x0100) {
            throw new RuntimeException(sprintf('Label printer rejected the job (IPP status 0x%04x)', $status));
        }

        // Pull job-id (integer attr 0x21, name "job-id") out of the response if present.
        $jobId = null;
        if (preg_match('/\x21\x00\x06job-id\x00\x04(.{4})/s', $raw, $m)) {
            $jobId = unpack('N', $m[1])[1];
        }

        return ['job_id' => $jobId, 'fake' => false];
    }

    private function attr(int $tag, string $name, string $value): string
    {
        return chr($tag).pack('n', strlen($name)).$name.pack('n', strlen($value)).$value;
    }

    private function buildPrintJob(string $printerUri, string $jobName, string $media, int $copies): string
    {
        $out = pack('CC', 1, 1).pack('n', 0x0002).pack('N', random_int(1, 0x7FFFFFFF));
        $out .= chr(0x01); // operation attributes
        $out .= $this->attr(0x47, 'attributes-charset', 'utf-8');
        $out .= $this->attr(0x48, 'attributes-natural-language', 'en');
        $out .= $this->attr(0x45, 'printer-uri', $printerUri);
        $out .= $this->attr(0x42, 'requesting-user-name', 'forgedesk');
        $out .= $this->attr(0x42, 'job-name', mb_substr($jobName, 0, 100));
        $out .= $this->attr(0x49, 'document-format', 'application/pdf');
        $out .= chr(0x02); // job attributes
        $out .= $this->attr(0x21, 'copies', pack('N', $copies));
        $out .= $this->attr(0x44, 'media', $media);
        $out .= chr(0x03); // end of attributes

        return $out;
    }
}
