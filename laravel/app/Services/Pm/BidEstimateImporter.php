<?php

namespace App\Services\Pm;

use App\Models\BidEstimate;
use App\Models\BusinessJob;
use App\Models\PmFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Parses a BidEstimate sheet through the worker and stores it relationally (header, summary, lines,
 * adjustments, warnings). Idempotent per (job, sheet, file hash, parser revision).
 */
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

        $estimate = DB::transaction(fn () => $this->store($parsed, $file, $job, $sheet, $by));
        $file->update(['status' => 'imported', 'error' => null, 'business_job_id' => $file->business_job_id ?? $job->id]);

        return [$estimate, $existing ? 'reimported' : 'imported'];
    }

    private function store(array $p, PmFile $file, BusinessJob $job, string $sheet, ?User $by): BidEstimate
    {
        $s = $p['summary'] ?? [];
        $m = $p['meta'] ?? [];
        $estimate = BidEstimate::updateOrCreate(['business_job_id' => $job->id, 'sheet' => $sheet], [
            'pm_file_id' => $file->id,
            'file_sha256' => $file->sha256,
            'parser_revision' => (int) ($p['parserRevision'] ?? 0),
            'project_name' => $m['projectName'] ?? null, 'gc' => $m['gc'] ?? null,
            'architect' => $m['architect'] ?? null, 'due' => $m['due'] ?? null,
            'contract_amount' => $s['contractAmount'] ?? null,
            'dollars_per_sf_glass' => $s['dollarsPerSquareFootGlass'] ?? null,
            'material' => $s['material'] ?? null, 'tax' => $s['tax'] ?? null,
            'large_sub' => $s['large_sub'] ?? null, 'other' => $s['other'] ?? null,
            'labor' => $s['labor'] ?? null, 'material_overhead' => $s['material_overhead'] ?? null,
            'imported_by' => $by?->id,
        ]);

        $estimate->lines()->delete();
        $estimate->adjustments()->delete();
        $estimate->warnings()->delete();

        // Usable lines (some carry a review reason), plus the codeless rows only the review list has.
        $rows = [];
        foreach (array_merge($p['lines'] ?? [], $p['reviewNeeded'] ?? []) as $l) {
            $rows[($l['section'] ?? '').'|'.$l['row']] ??= [
                'bid_estimate_id' => $estimate->id,
                'section' => $l['section'] ?? 'material',
                'row' => $l['row'],
                'who' => $l['who'] ?? null,
                'cost_code' => $l['costCode'] ?? null,
                'additional_cost_code' => $l['additionalCostCode'] ?? null,
                'description' => $l['description'] ?? null,
                'qty' => $l['qty'] ?? null,
                'unit_price' => $l['unitPrice'] ?? null,
                'total' => $l['total'] ?? null,
                'confidence' => $l['confidence'] ?? null,
                'review_reason' => $l['reviewReason'] ?? null,
            ];
        }
        foreach (array_chunk(array_values($rows), 200) as $chunk) {
            $estimate->lines()->insert($chunk);
        }

        foreach ($p['adjustments'] ?? [] as $a) {
            $estimate->adjustments()->create([
                'section' => $a['section'], 'row' => $a['row'], 'description' => $a['description'] ?? null,
                'rate' => $a['rate'] ?? null, 'base' => $a['base'] ?? null, 'amount' => $a['amount'] ?? null,
            ]);
        }
        foreach ($p['warnings'] ?? [] as $w) {
            $estimate->warnings()->create([
                'type' => $w['type'] ?? 'warning', 'section' => $w['section'] ?? null,
                'computed_from_lines' => $w['computedFromLines'] ?? null,
                'stated_in_sheet' => $w['statedInSheet'] ?? null, 'note' => $w['note'] ?? null,
            ]);
        }

        return $estimate;
    }
}
