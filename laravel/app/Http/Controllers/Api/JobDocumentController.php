<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\JobDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Files attached to a job (not a work order). Types: SOF, EZ Estimate,
 * Purchase Order, Other. Read is gated by jobs.documents.view; upload/delete by
 * jobs.documents.manage (see routes). Files live on the local/private disk.
 */
class JobDocumentController extends Controller
{
    public function index(int $jobId)
    {
        BusinessJob::findOrFail($jobId);

        $docs = JobDocument::with('uploader:id,name')
            ->where('business_job_id', $jobId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($d) => $this->format($d));

        return response()->json([
            'documents' => $docs,
            'types' => JobDocument::TYPES,
        ]);
    }

    public function store(Request $request, int $jobId)
    {
        BusinessJob::findOrFail($jobId);

        $data = $request->validate([
            'doc_type' => ['required', Rule::in(array_keys(JobDocument::TYPES))],
            'label' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('doc_type') === 'other')],
            'file' => 'required|file|max:25600|mimes:pdf,xlsx,xlsm,xls,csv,doc,docx,png,jpg,jpeg,webp,gif,txt',
        ]);

        try {
            $file = $request->file('file');
            $safeName = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'dat');
            $path = $file->storeAs("job_documents/{$jobId}", $safeName, 'local');

            $doc = JobDocument::create([
                'business_job_id' => $jobId,
                'doc_type' => $data['doc_type'],
                'label' => trim((string) ($data['label'] ?? '')) ?: null,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'uploaded_by' => $request->user()?->id,
            ]);

            return response()->json($this->format($doc->load('uploader:id,name')), 201);
        } catch (\Exception $e) {
            Log::error('JobDocumentController@store failed', ['job_id' => $jobId, 'message' => $e->getMessage()]);

            return response()->json(['error' => 'Upload failed'], 500);
        }
    }

    public function download(int $jobId, int $documentId)
    {
        $doc = JobDocument::where('business_job_id', $jobId)->findOrFail($documentId);

        if (! Storage::disk('local')->exists($doc->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($doc->file_path, $doc->original_name);
    }

    public function destroy(int $jobId, int $documentId)
    {
        $doc = JobDocument::where('business_job_id', $jobId)->findOrFail($documentId);

        Storage::disk('local')->delete($doc->file_path);
        $doc->delete();

        return response()->json(['deleted' => $documentId]);
    }

    private function format(JobDocument $d): array
    {
        return [
            'id' => $d->id,
            'business_job_id' => $d->business_job_id,
            'doc_type' => $d->doc_type,
            'doc_type_label' => JobDocument::TYPES[$d->doc_type] ?? $d->doc_type,
            'label' => $d->label,
            'original_name' => $d->original_name,
            'file_size' => $d->file_size,
            'file_mime' => $d->file_mime,
            'uploaded_by_name' => $d->uploader?->name,
            'download_url' => "/api/v1/business-jobs/{$d->business_job_id}/documents/{$d->id}/download",
            'created_at' => $d->created_at->toIso8601String(),
        ];
    }
}
