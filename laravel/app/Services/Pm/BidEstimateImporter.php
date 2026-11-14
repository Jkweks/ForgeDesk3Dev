<?php

namespace App\Services\Pm;

use App\Models\BidEstimate;
use App\Models\BusinessJob;
use App\Models\PmFile;
use App\Models\User;

/** Parses a BidEstimate sheet through the worker and stores it. Idempotent per (job, sheet, file hash, parser revision). */
class BidEstimateImporter
{
    /** Keep in step with PARSER_REVISION in pm-worker/estimate_parser.py. Older stored imports are flagged stale. */
    public const CURRENT_PARSER_REVISION = 550;

    public function __construct(private PmWorkerClient $worker, private PmFileIngestor $files) {}

    /** @return array{0: BidEstimate, 1: string} the estimate and 'imported' | 'unchanged' | 'reimported' */
    public function import(PmFile $file, BusinessJob $job, string $sheet, ?User $by = null, bool $force = false): array
    {
        $existing = BidEstimate::where('business_job_id', $job->id)->where('sheet', $sheet)->first();
        if ($existing && ! $force
            && $existing->file_sha256 === $file->sha256
            && $existing->parser_revision >= self::CURRENT_PARSER_REVISION) {
            return [$existing, 'unchanged']; // nothing to do: no worker call, no parse
        }

        try {
            $parsed = $this->worker->parseEstimate($this->files->bytes($file), $sheet);
        } catch (PmWorkerDataException $e) {
            $file->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }

        $estimate = BidEstimate::updateOrCreate(
            ['business_job_id' => $job->id, 'sheet' => $sheet],
            [
                'pm_file_id' => $file->id,
                'file_sha256' => $file->sha256,
                'parser_revision' => (int) ($parsed['parserRevision'] ?? 0),
                'contract_amount' => $parsed['summary']['contractAmount'] ?? null,
                'line_count' => (int) ($parsed['lineCount'] ?? count($parsed['lines'] ?? [])),
                'review_count' => (int) ($parsed['reviewCount'] ?? count($parsed['reviewNeeded'] ?? [])),
                'data' => $parsed,
                'imported_by' => $by?->id,
            ]
        );
        $file->update(['status' => 'imported', 'error' => null, 'business_job_id' => $file->business_job_id ?? $job->id]);

        return [$estimate, $existing ? 'reimported' : 'imported'];
    }
}
