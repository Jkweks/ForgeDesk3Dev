<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdUser;
use App\Models\FdWoElevation;
use App\Models\QualityReport;
use App\Services\ElevationMatcherService;
use App\Services\QualityReportPdfExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quality-issue reports ingested from uploaded PDFs. store() extracts the
 * form fields and best-guesses the elevation immediately; a manager/admin
 * then reviews, edits, reassigns, and verifies/rejects from the queue.
 */
class QualityReportController extends Controller
{
    /**
     * Flat list of elevations on open (active/on_hold) work orders, for the
     * manual elevation picker on upload/edit. Kept lightweight — no stage
     * detail — since it's only used to populate a dropdown.
     */
    public function elevationOptions()
    {
        $elevations = FdWoElevation::query()
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', ['active', 'on_hold']))
            ->with(['workOrder.businessJob'])
            ->orderBy('elevation_tag')
            ->get()
            ->map(fn (FdWoElevation $e) => [
                'id' => $e->id,
                'elevation_tag' => $e->elevation_tag,
                'work_order_id' => $e->work_order_id,
                'work_order_label' => $e->workOrder?->releaseLabel(),
                'business_job_id' => $e->workOrder?->business_job_id,
                'business_job_name' => $e->workOrder?->businessJob?->job_name,
            ])
            ->values();

        return response()->json(['data' => $elevations]);
    }

    public function index(Request $request)
    {
        $reports = $this->filteredQuery($request)->get()->map(fn ($r) => $this->format($r));

        return response()->json(['data' => $reports]);
    }

    public function exportCsv(Request $request)
    {
        $reports = $this->filteredQuery($request)->get();

        $headers = ['Report Date', 'Job', 'Work Order', 'Elevation', 'Replacement Needed', 'Problem Type', 'Reported By', 'Status', 'Verified By', 'Verified At', 'Reviewed By', 'Reviewed At'];

        $rows = $reports->map(fn (QualityReport $r) => [
            $r->report_date?->toDateString(),
            $this->jobNameFor($r),
            $r->workOrder?->releaseLabel(),
            $this->elevationTagFor($r),
            $r->replacement_needed === true ? 'Yes' : ($r->replacement_needed === false ? 'No' : ''),
            $r->problem_type,
            $r->inspector_name,
            $r->status,
            $r->verifier?->name,
            $r->verified_at?->toDateTimeString(),
            $r->reviewer?->name,
            $r->reviewed_at?->toDateTimeString(),
        ]);

        return $this->generateCsv($rows, 'quality-reports', $headers);
    }

    public function exportPdf(Request $request)
    {
        $reports = $this->filteredQuery($request)->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.quality-reports', [
            'reports' => $reports,
            'jobNames' => $reports->mapWithKeys(fn ($r) => [$r->id => $this->jobNameFor($r)]),
            'elevationTags' => $reports->mapWithKeys(fn ($r) => [$r->id => $this->elevationTagFor($r)]),
        ]);
        $pdf->setPaper('letter', 'landscape');

        return $pdf->stream('quality-reports-'.now()->format('Y-m-d').'.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = QualityReport::with([
            'elevation.elevationType',
            'workOrder.businessJob',
            'uploader:id,name',
            'verifier:id,name',
            'reviewer:id,name',
        ]);

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->input('status')));
        }
        if ($request->filled('elevation_id')) {
            $query->where('elevation_id', $request->input('elevation_id'));
        }
        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->input('work_order_id'));
        }
        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', $request->input('uploaded_by'));
        }
        if ($request->filled('verified_by')) {
            $query->where('verified_by', $request->input('verified_by'));
        }
        if ($request->filled('date_from')) {
            $query->where('report_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('report_date', '<=', $request->input('date_to'));
        }

        return $query->orderByDesc('created_at');
    }

    private function generateCsv($rows, string $filename, array $headers)
    {
        $filename = $filename.'_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($rows, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function show(int $id)
    {
        $report = QualityReport::with([
            'elevation.elevationType',
            'elevation.stages.assignees',
            'workOrder.businessJob',
            'uploader:id,name',
            'matchedByUser:id,name',
            'verifier:id,name',
            'reviewer:id,name',
            'files',
        ])->findOrFail($id);

        return response()->json($this->format($report, detailed: true));
    }

    public function store(Request $request, QualityReportPdfExtractor $extractor, ElevationMatcherService $matcher)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
            'elevation_id' => 'nullable|integer|exists:fd_wo_elevations,id',
            // Manual override for issues that predate ForgeDesk tracking (the "Pre-Forge"
            // picker option) — skips auto-matching and stamps this text as the reference
            // instead of a real elevation. Ignored when elevation_id is also given.
            'elevation_tag_guess' => 'nullable|string|max:255',
        ]);

        try {
            $file = $request->file('file');

            $report = QualityReport::create([
                'status' => 'pending_review',
                'auto_matched' => false,
                'uploaded_by' => $request->user()?->id,
            ]);

            $safeName = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $path = $file->storeAs("quality_reports/{$report->id}", $safeName, 'local');

            $report->files()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'uploaded_by' => $request->user()?->id,
            ]);

            $extracted = $extractor->extract(Storage::disk('local')->path($path));

            $report->update([
                'report_date' => $extracted['report_date'],
                'completed_at' => $extracted['completed_at'],
                'inspector_name' => $extracted['inspector_name'],
                'problem_type' => $extracted['problem_type'],
                'replacement_needed' => $extracted['replacement_needed'],
                'issue_description' => $extracted['issue_description'],
                'elevation_tag_guess' => $extracted['elevation_tag_guess'],
                'raw_extracted_text' => $extracted['raw_extracted_text'],
                'extracted_fields' => $extracted,
            ]);

            if ($request->filled('elevation_id')) {
                $report->reassignElevation((int) $request->input('elevation_id'), $request->user()->id);
            } elseif ($request->filled('elevation_tag_guess')) {
                $report->update([
                    'elevation_tag_guess' => $request->input('elevation_tag_guess'),
                    'auto_matched' => false,
                    'matched_by_user_id' => $request->user()->id,
                ]);
            } else {
                $this->applyMatch($report, $matcher, $extracted);
            }

            $report->load(['elevation.elevationType', 'elevation.stages.assignees', 'workOrder.businessJob', 'uploader:id,name', 'files']);

            return response()->json($this->format($report, detailed: true), 201);
        } catch (\Exception $e) {
            Log::error('QualityReportController@store failed', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Upload failed'], 500);
        }
    }

    public function rematch(int $id, ElevationMatcherService $matcher)
    {
        $report = QualityReport::findOrFail($id);

        $this->applyMatch($report, $matcher, [
            'job_text' => $report->extracted_fields['job_text'] ?? null,
            'elevation_tag_guess' => $report->elevation_tag_guess,
            'report_date' => $report->report_date?->toDateString(),
        ]);

        $report->load(['elevation.elevationType', 'elevation.stages.assignees', 'workOrder.businessJob', 'uploader:id,name', 'files']);

        return response()->json($this->format($report, detailed: true));
    }

    private function applyMatch(QualityReport $report, ElevationMatcherService $matcher, array $extracted): void
    {
        $match = $matcher->match(
            $extracted['job_text'] ?? null,
            $extracted['elevation_tag_guess'] ?? null,
            $extracted['report_date'] ?? null,
        );

        $report->update([
            'elevation_id' => $match['elevation_id'],
            'work_order_id' => $match['work_order_id'],
            'match_confidence' => $match['confidence'],
            'match_candidates' => $match['candidates'],
            'auto_matched' => true,
            'matched_by_user_id' => null,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $report = QualityReport::findOrFail($id);

        $data = $request->validate([
            'elevation_id' => 'nullable|integer|exists:fd_wo_elevations,id',
            'elevation_tag_guess' => 'sometimes|nullable|string|max:255',
            'report_date' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'inspector_name' => 'nullable|string|max:255',
            'problem_type' => 'nullable|string|max:60',
            'replacement_needed' => 'nullable|boolean',
            'issue_description' => 'nullable|string',
        ]);

        if ($request->has('elevation_id')) {
            if ($request->filled('elevation_id')) {
                if ((int) $request->input('elevation_id') !== $report->elevation_id) {
                    $report->reassignElevation((int) $request->input('elevation_id'), $request->user()->id);
                }
            } elseif ($report->elevation_id !== null) {
                // Explicitly cleared (e.g. reassigned to "Unassigned" or "Pre-Forge").
                $report->update([
                    'elevation_id' => null,
                    'work_order_id' => null,
                    'auto_matched' => false,
                    'matched_by_user_id' => $request->user()->id,
                ]);
            }
        }

        $report->update(array_diff_key($data, ['elevation_id' => null]));

        $report->load(['elevation.elevationType', 'elevation.stages.assignees', 'workOrder.businessJob', 'uploader:id,name', 'files']);

        return response()->json($this->format($report, detailed: true));
    }

    public function verify(Request $request, int $id)
    {
        $report = QualityReport::findOrFail($id);

        if ($report->status !== 'pending_review') {
            return response()->json(['error' => 'Only a pending report can be verified.'], 422);
        }

        $report->verify($request->user()->id);

        return response()->json($this->format($report->fresh(), detailed: true));
    }

    public function reject(Request $request, int $id)
    {
        $report = QualityReport::findOrFail($id);

        if ($report->status !== 'pending_review') {
            return response()->json(['error' => 'Only a pending report can be rejected.'], 422);
        }

        $data = $request->validate(['reason' => 'nullable|string']);
        $report->reject($request->user()->id, $data['reason'] ?? null);

        return response()->json($this->format($report->fresh(), detailed: true));
    }

    public function review(Request $request, int $id)
    {
        $report = QualityReport::findOrFail($id);

        if ($report->status !== 'verified') {
            return response()->json(['error' => 'Only a verified report can be marked reviewed.'], 422);
        }

        $report->review($request->user()->id);

        return response()->json($this->format($report->fresh(), detailed: true));
    }

    public function destroy(int $id)
    {
        $report = QualityReport::with('files')->findOrFail($id);

        if (in_array($report->status, ['verified', 'reviewed'], true)) {
            return response()->json(['error' => 'A verified or reviewed report cannot be deleted.'], 422);
        }

        foreach ($report->files as $file) {
            Storage::disk('local')->delete($file->file_path);
        }
        $report->delete();

        return response()->json(['deleted' => $id]);
    }

    public function downloadFile(int $id, int $fileId)
    {
        $report = QualityReport::findOrFail($id);
        $file = $report->files()->findOrFail($fileId);

        if (! Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }

    /** Same file as downloadFile(), but served inline for the in-app PDF viewer instead of forcing a download. */
    public function viewFile(int $id, int $fileId)
    {
        $report = QualityReport::findOrFail($id);
        $file = $report->files()->findOrFail($fileId);

        if (! Storage::disk('local')->exists($file->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->file(Storage::disk('local')->path($file->file_path), [
            'Content-Type' => $file->file_mime ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($file->original_name).'"',
        ]);
    }

    private function format(QualityReport $r, bool $detailed = false): array
    {
        $data = [
            'id' => $r->id,
            'status' => $r->status,
            'elevation_id' => $r->elevation_id,
            'elevation_tag' => $this->elevationTagFor($r),
            'elevation_type' => $r->elevation?->elevationType?->name,
            'work_order_id' => $r->work_order_id,
            'work_order_label' => $r->workOrder?->releaseLabel(),
            'business_job_id' => $r->workOrder?->business_job_id,
            'business_job_name' => $this->jobNameFor($r),
            'report_date' => $r->report_date?->toDateString(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'inspector_name' => $r->inspector_name,
            'problem_type' => $r->problem_type,
            'replacement_needed' => $r->replacement_needed,
            'issue_description' => $r->issue_description,
            'elevation_tag_guess' => $r->elevation_tag_guess,
            'auto_matched' => $r->auto_matched,
            'match_confidence' => $r->match_confidence,
            'uploaded_by_name' => $r->uploader?->name,
            'verified_by_name' => $r->verifier?->name,
            'verified_at' => $r->verified_at?->toIso8601String(),
            'reviewed_by_name' => $r->reviewer?->name,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'rejected_reason' => $r->rejected_reason,
            'created_at' => $r->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['raw_extracted_text'] = $r->raw_extracted_text;
            $data['extracted_fields'] = $r->extracted_fields;
            $data['match_candidates'] = $r->match_candidates;
            $data['employee_initials'] = $this->employeeInitials($r->elevation);
            $data['files'] = $r->files->map(fn ($f) => [
                'id' => $f->id,
                'original_name' => $f->original_name,
                'file_size' => $f->file_size,
                'file_mime' => $f->file_mime,
                'download_url' => "/api/v1/quality-reports/{$r->id}/files/{$f->id}/download",
            ]);
        }

        return $data;
    }

    /**
     * Every fd_user who touched the elevation (completed it, was assigned to
     * a stage, or completed a stage) — a single set of initials if it's one
     * person throughout, otherwise "Mixed" since attributing the issue to
     * any one of them would be misleading.
     */
    /** Real elevation tag when matched, else the reference text kept from ingestion/import (never blank for a historical, unmatched report). */
    private function elevationTagFor(QualityReport $r): ?string
    {
        return $r->elevation?->elevation_tag ?? $r->elevation_tag_guess;
    }

    /** Real business job name when matched, else the reference job text kept from ingestion/import. */
    private function jobNameFor(QualityReport $r): ?string
    {
        return $r->workOrder?->businessJob?->job_name ?? ($r->extracted_fields['job_text'] ?? null);
    }

    private function employeeInitials(?FdWoElevation $elevation): ?string
    {
        if (! $elevation) {
            return null;
        }

        $userIds = collect([$elevation->completed_by_id]);
        foreach ($elevation->stages as $stage) {
            $userIds->push($stage->completed_by_id);
            $userIds = $userIds->merge($stage->assignees->pluck('id'));
        }

        $ids = $userIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return null;
        }
        if ($ids->count() === 1) {
            return FdUser::find($ids->first())?->initials;
        }

        return 'Mixed';
    }
}
