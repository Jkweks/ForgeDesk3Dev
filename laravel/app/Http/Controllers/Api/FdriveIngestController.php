<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\PmFile;
use App\Services\Pm\PmFileIngestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Receives "file appeared/changed" events from the Windows-side F: watcher (service token, no user
 * session), and exposes the review inbox for files the ingest could not place on its own.
 */
class FdriveIngestController extends Controller
{
    public function ingest(Request $request, PmFileIngestService $ingest)
    {
        $token = config('services.fdrive_ingest.token');
        abort_unless($token && hash_equals($token, (string) $request->header('X-Ingest-Token')), 401, 'Invalid ingest token.');

        $data = $request->validate([
            'path' => 'required|string|max:1024', 'size' => 'required|integer|min:0',
            'sha256' => 'nullable|string|size:64', 'mtime' => 'nullable|date', 'event' => 'nullable|string|max:20',
        ]);
        if (($data['event'] ?? '') === 'deleted') {
            return response()->json(['action' => 'ignored']); // we keep our copy and history; nothing to do
        }
        $result = $ingest->handle($data);

        return response()->json(['action' => $result['action'], 'file_id' => $result['file']?->id]);
    }

    /** Files waiting for a person: could not be classified, have no job, or failed to import. */
    public function inbox(Request $request)
    {
        return response()->json(PmFile::whereIn('status', ['review', 'failed'])->with('job:id,job_number,job_name')
            ->orderByDesc('id')->limit(300)->get(['id', 'doc_type', 'original_name', 'fdrive_path', 'status', 'error', 'business_job_id', 'size', 'created_at']));
    }

    /** A person says what a file is and which job it belongs to; import then runs for the types that have one. */
    public function assign(Request $request, int $id, PmFileIngestService $ingest)
    {
        $file = PmFile::findOrFail($id);
        $data = $request->validate([
            'business_job_id' => 'required|integer',
            'doc_type' => ['required', Rule::in(['bid_estimate', 'drawing_set', 'submittal_document', 'other'])],
        ]);
        $job = BusinessJob::withoutGlobalScopes()->findOrFail($data['business_job_id']);
        $file->update(['doc_type' => $data['doc_type'], 'business_job_id' => $job->id, 'status' => 'new', 'error' => null]);

        $action = 'filed';
        if ($data['doc_type'] === 'bid_estimate') {
            try {
                app(\App\Services\Pm\BidEstimateImporter::class)->import($file, $job, 'CONTRACT', $request->user());
                $action = 'imported';
            } catch (\Throwable $e) {
                $file->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
                $action = 'failed';
            }
        } elseif ($data['doc_type'] === 'drawing_set') {
            $set = \App\Models\DrawingSet::firstOrCreate(['business_job_id' => $job->id, 'pm_file_id' => $file->id], ['status' => 'queued', 'created_by' => $request->user()->id]);
            if ($set->wasRecentlyCreated) {
                \App\Jobs\ImportDrawingSet::dispatch($set);
            }
            $file->update(['status' => 'imported']);
            $action = 'queued';
        }

        return response()->json(['action' => $action, 'file' => $file->fresh()]);
    }
}
