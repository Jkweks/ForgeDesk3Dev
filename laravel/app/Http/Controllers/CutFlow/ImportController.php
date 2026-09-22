<?php

namespace App\Http\Controllers\CutFlow;

use App\Http\Controllers\Controller;
use App\Services\CutFlow\CutlistIngestService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function show()
    {
        return view('cutflow.import');
    }

    /**
     * Expects a CSV with a header row containing (in any order):
     * part_id (or name), finish, dimension_in, qty, job, work_order, phase,
     * row, column, description, leftcutangle, rightcutangle
     *
     * All rows in one CSV are attached to a single job (the CSV's `job`
     * column, falling back to the uploaded filename) — one job per upload.
     * See CutlistIngestService::ingest() for the identity/merge rules.
     */
    public function store(Request $request, CutlistIngestService $ingest)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv');
        $rawRows = $ingest->parseCsv($file);
        $fallbackJobName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $result = $ingest->ingest($rawRows, $fallbackJobName);

        return redirect()->route('cutflow.dashboard', ['job' => $result['job']->id])
            ->with('status', "Imported {$result['line_count']} cut-list lines into \"{$result['job']->name}\".");
    }
}
