<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Submits a PDF to a CUPS queue with a minimal IPP/1.1 Print-Job request over HTTP — no
 * cups-client binary needed in the container. Configured by services.label_printer.
 *
 * PDFs are rasterized here with Ghostscript (1-bit PWG raster at the printer's dpi) before sending: the
 * print server then only passes pixels through instead of parsing fonts and rendering the PDF itself,
 * which on a small CUPS box took 20-30s per label. Falls back to sending the PDF if rasterizing fails.
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

        $format = 'application/pdf';
        $doc = $pdf;
        if (! empty($cfg['rasterize'])) {
            try {
                $doc = $this->rasterize($pdf, (int) ($cfg['dpi'] ?? 203));
                $format = 'image/pwg-raster';
            } catch (\Throwable $e) {
                Log::warning('LabelPrinter: rasterize failed, sending PDF instead: '.$e->getMessage());
            }
        }

        $body = $this->buildPrintJob($printerUri, $jobName, $cfg['media'], max(1, $copies), $format).$doc;

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

    /**
     * PDF -> 1-bit PWG raster (image/pwg-raster) via Ghostscript. Page size comes from the PDF.
     */
    public function rasterize(string $pdf, int $dpi = 203): string
    {
        $in = tempnam(sys_get_temp_dir(), 'lbl');
        $out = $in.'.pwg';
        try {
            file_put_contents($in, $pdf);
            $p = new Process([
                'gs', '-q', '-dBATCH', '-dNOPAUSE', '-dSAFER', '-sDEVICE=pwgraster', "-r{$dpi}",
                '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', '-sOutputFile='.$out, $in,
            ]);
            $p->setTimeout(20)->run();
            if (! $p->isSuccessful() || ! is_file($out) || filesize($out) < 1796) {
                throw new RuntimeException('gs failed: '.trim($p->getErrorOutput() ?: $p->getOutput()));
            }

            return file_get_contents($out);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    private function attr(int $tag, string $name, string $value): string
    {
        return chr($tag).pack('n', strlen($name)).$name.pack('n', strlen($value)).$value;
    }

    private function buildPrintJob(string $printerUri, string $jobName, string $media, int $copies, string $format = 'application/pdf'): string
    {
        $out = pack('CC', 1, 1).pack('n', 0x0002).pack('N', random_int(1, 0x7FFFFFFF));
        $out .= chr(0x01); // operation attributes
        $out .= $this->attr(0x47, 'attributes-charset', 'utf-8');
        $out .= $this->attr(0x48, 'attributes-natural-language', 'en');
        $out .= $this->attr(0x45, 'printer-uri', $printerUri);
        $out .= $this->attr(0x42, 'requesting-user-name', 'forgedesk');
        $out .= $this->attr(0x42, 'job-name', mb_substr($jobName, 0, 100));
        $out .= $this->attr(0x49, 'document-format', $format);
        $out .= chr(0x02); // job attributes
        $out .= $this->attr(0x21, 'copies', pack('N', $copies));
        $out .= $this->attr(0x44, 'media', $media);
        $out .= chr(0x03); // end of attributes

        return $out;
    }
}
