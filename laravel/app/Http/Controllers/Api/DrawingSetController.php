<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportDrawingSet;
use App\Models\DrawingSet;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;

/** Shop-drawing sets for a Project Management job (feature #21). Results are suggestions, never auto-applied. */
class DrawingSetController extends Controller
{
    private function set(Request $request, int $jobId, int $id): DrawingSet
    {
        $job = PmJobScope::find($request->user(), $jobId);

        return DrawingSet::where('business_job_id', $job->id)->findOrFail($id);
    }

    private function present(DrawingSet $s, bool $full = false): array
    {
        $s->load('file:id,original_name,size');
        if ($full) {
            $s->load(['elevations.dims', 'elevations.glassTags', 'elevations.sheets', 'elevations.marks', 'warnings']);
        }

        return $s->toArray();
    }

    public function index(Request $request, int $jobId)
    {
        $job = PmJobScope::find($request->user(), $jobId);

        return response()->json(DrawingSet::where('business_job_id', $job->id)->withCount('elevations')->with('file:id,original_name,size')->orderByDesc('id')->get());
    }

    public function show(Request $request, int $jobId, int $id)
    {
        return response()->json($this->present($this->set($request, $jobId, $id), true));
    }

    /** Upload a drawing set PDF and queue the import (a large set takes minutes). */
    public function store(Request $request, int $jobId, PmFileIngestor $files)
    {
        $job = PmJobScope::find($request->user(), $jobId);
        $request->validate(['file' => 'required|file|max:204800|extensions:pdf']);

        [$file] = $files->registerUpload($request->file('file'), 'drawing_set', $job, $request->user());
        $set = DrawingSet::firstOrCreate(
            ['business_job_id' => $job->id, 'pm_file_id' => $file->id],
            ['status' => 'queued', 'created_by' => $request->user()->id]
        );
        if ($set->wasRecentlyCreated) {
            ImportDrawingSet::dispatch($set);
        }

        return response()->json($this->present($set->fresh()) + ['already_uploaded' => ! $set->wasRecentlyCreated], $set->wasRecentlyCreated ? 202 : 200);
    }

    /**
     * The sheet page as an image, for the size-check viewer. Rendered by the worker once per set, page and
     * resolution, then served from storage.
     */
    public function pageImage(Request $request, int $jobId, int $id, int $page, PmFileIngestor $files, \App\Services\Pm\PmWorkerClient $worker)
    {
        $set = $this->set($request, $jobId, $id);
        $dpi = max(60, min(160, (int) $request->query('dpi', 110)));
        abort_unless($set->elevations()->whereHas('sheets', fn ($q) => $q->where('page', $page))->exists(), 404);

        $path = "pm_files/pages/{$set->file->sha256}-p{$page}-{$dpi}.png";
        $disk = \Illuminate\Support\Facades\Storage::disk(PmFileIngestor::DISK);
        if (! $disk->exists($path)) {
            try {
                $disk->put($path, $worker->pageImage($files->bytes($set->file), $page, $dpi));
            } catch (\App\Services\Pm\PmWorkerDataException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $e) {
                return response()->json(['message' => 'The drawing worker is unavailable: '.$e->getMessage()], 503);
            }
        }

        return response($disk->get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Confirm what the parser read (no body), correct it (confirmed_value), or clear the decision (confirmed_value null).
     * Several marks can be done at once with `ids` (confirm all on a sheet).
     */
    public function confirmMarks(Request $request, int $jobId, int $id)
    {
        $set = $this->set($request, $jobId, $id);
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer',
            'action' => 'required|in:confirm,correct,clear', 'value' => 'required_if:action,correct|nullable|numeric|min:0|max:100000',
        ]);
        $marks = \App\Models\DrawingDimMark::whereIn('id', $data['ids'])
            ->whereHas('elevation', fn ($q) => $q->where('drawing_set_id', $set->id))->get();
        abort_if($marks->count() !== count(array_unique($data['ids'])), 404);
        abort_if($data['action'] === 'correct' && $marks->count() !== 1, 422, 'Correct one value at a time.');

        foreach ($marks as $m) {
            $m->update($data['action'] === 'clear'
                ? ['confirmed_value' => null, 'confirmed_by' => null, 'confirmed_at' => null]
                : ['confirmed_value' => $data['action'] === 'confirm' ? $m->value : $data['value'], 'confirmed_by' => $request->user()->id, 'confirmed_at' => now()]);
        }

        return response()->json($marks->fresh()->values());
    }

    /** Read it again, e.g. after a parser update or a failure. */
    public function reimport(Request $request, int $jobId, int $id)
    {
        $set = $this->set($request, $jobId, $id);
        // A set stuck on 'running' (the worker died) can be re-read once it has been silent for longer than any import takes.
        abort_if($set->status === 'running' && $set->updated_at->gt(now()->subMinutes(20)), 422, 'This set is being read right now.');
        $set->update(['status' => 'queued', 'error' => null]);
        ImportDrawingSet::dispatch($set);

        return response()->json($this->present($set->fresh()), 202);
    }

    public function destroy(Request $request, int $jobId, int $id)
    {
        $this->set($request, $jobId, $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
