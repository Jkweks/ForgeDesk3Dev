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
            $s->load(['elevations.dims', 'elevations.glassTags', 'warnings']);
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

    /** Read it again, e.g. after a parser update or a failure. */
    public function reimport(Request $request, int $jobId, int $id)
    {
        $set = $this->set($request, $jobId, $id);
        abort_if($set->status === 'running', 422, 'This set is being read right now.');
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
