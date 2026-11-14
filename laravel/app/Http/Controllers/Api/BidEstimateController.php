<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BidEstimate;
use App\Models\BidEstimateOverride;
use App\Models\BusinessJob;
use App\Models\CostCode;
use App\Models\PmFile;
use App\Models\Scopes\FabricationVisibleScope;
use App\Services\Pm\BidEstimateImporter;
use App\Services\Pm\BidEstimatePresenter;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmWorkerClient;
use App\Services\Pm\PmWorkerDataException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;

/**
 * BidEstimate import for Project Management jobs. Manual upload today; the F: watcher later
 * registers files through PmFileIngestor and calls the same import.
 */
class BidEstimateController extends Controller
{
    public function __construct(
        private PmFileIngestor $files,
        private PmWorkerClient $worker,
        private BidEstimateImporter $importer,
        private BidEstimatePresenter $presenter,
    ) {}

    private function job(int $id): BusinessJob
    {
        return \App\Services\Pm\PmJobScope::find(auth()->user(), $id);
    }

    public function index(int $jobId)
    {
        $job = $this->job($jobId);

        return response()->json(BidEstimate::where('business_job_id', $job->id)->orderBy('sheet')->get()
            ->map(fn ($e) => [
                'id' => $e->id, 'sheet' => $e->sheet, 'imported_at' => $e->updated_at,
                'contract_amount' => $e->contract_amount, 'line_count' => $e->lines()->count(),
                'review_count' => $e->lines()->whereNotNull('review_reason')->count(),
                'parserStale' => $e->parser_revision < BidEstimateImporter::CURRENT_PARSER_REVISION,
            ]));
    }

    /** Upload a workbook; returns the file record and the sheet tabs to choose from. */
    public function upload(Request $request, int $jobId)
    {
        $job = $this->job($jobId);
        $request->validate(['file' => 'required|file|max:51200|extensions:xls,xlsx,xlsm']);

        [$file, $known] = $this->files->registerUpload($request->file('file'), 'bid_estimate', $job, $request->user());

        return $this->withWorker(fn () => response()->json([
            'file' => $file, 'already_uploaded' => $known,
            'sheets' => $this->worker->estimateSheets($this->files->bytes($file)),
        ], $known ? 200 : 201));
    }

    /** Import (or re-import) one sheet of a registered file. Skips the parse when nothing changed. */
    public function import(Request $request, int $jobId)
    {
        $job = $this->job($jobId);
        $data = $request->validate(['pm_file_id' => 'required|integer', 'sheet' => 'required|string|max:100', 'force' => 'boolean']);
        $file = PmFile::where('doc_type', 'bid_estimate')->findOrFail($data['pm_file_id']);

        return $this->withWorker(function () use ($job, $file, $data, $request) {
            [$estimate, $outcome] = $this->importer->import($file, $job, $data['sheet'], $request->user(), (bool) ($data['force'] ?? false));

            return response()->json(['outcome' => $outcome, 'estimate' => $this->presenter->present($estimate)]);
        });
    }

    public function show(int $jobId, string $sheet)
    {
        $job = $this->job($jobId);
        $estimate = BidEstimate::with(['lines', 'adjustments', 'warnings'])->where('business_job_id', $job->id)->where('sheet', $sheet)->firstOrFail();

        return response()->json($this->presenter->present($estimate));
    }

    /** A PM's decision about one parsed row: fix its cost code or ignore it. Persists across re-imports. */
    public function override(Request $request, int $jobId, string $sheet)
    {
        $job = $this->job($jobId);
        $estimate = BidEstimate::where('business_job_id', $job->id)->where('sheet', $sheet)->firstOrFail();
        $data = $request->validate([
            'section' => 'required|in:material,large_sub,other,labor',
            'row' => 'required|integer|min:1',
            'action' => 'required|in:fix,ignore',
            'cost_code' => 'required_if:action,fix|nullable|integer|exists:cost_codes,code',
            'note' => 'nullable|string|max:255',
        ]);

        BidEstimateOverride::updateOrCreate(
            ['business_job_id' => $job->id, 'sheet' => $sheet, 'section' => $data['section'], 'row' => $data['row']],
            ['action' => $data['action'], 'cost_code' => $data['action'] === 'fix' ? $data['cost_code'] : null,
                'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]
        );

        return response()->json($this->presenter->present($estimate));
    }

    public function restore(int $jobId, string $sheet, string $section, int $row)
    {
        $job = $this->job($jobId);
        $estimate = BidEstimate::where('business_job_id', $job->id)->where('sheet', $sheet)->firstOrFail();
        BidEstimateOverride::where(['business_job_id' => $job->id, 'sheet' => $sheet, 'section' => $section, 'row' => $row])->delete();

        return response()->json($this->presenter->present($estimate));
    }

    public function costCodes()
    {
        return response()->json(CostCode::orderBy('code')->get());
    }

    private function withWorker(callable $fn)
    {
        try {
            return $fn();
        } catch (PmWorkerDataException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ConnectionException|\RuntimeException $e) {
            return response()->json(['message' => 'The estimate worker is unavailable: '.$e->getMessage()], 503);
        }
    }
}
