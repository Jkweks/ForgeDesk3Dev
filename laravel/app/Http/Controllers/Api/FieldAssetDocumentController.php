<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FieldAsset;
use App\Models\FieldAssetDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Asset documents live on the private `local` disk (titles, insurance and certs are
 * sensitive) and are served through an authenticated download route only.
 */
class FieldAssetDocumentController extends Controller
{
    public const MIMES = 'pdf,jpg,jpeg,png,webp,heic,doc,docx,xls,xlsx,csv,txt';

    public function index(Request $request, int $assetId)
    {
        FieldAsset::findOrFail($assetId);
        $q = FieldAssetDocument::where('field_asset_id', $assetId);
        foreach (['work_order_id', 'compliance_id', 'inspection_id', 'doc_type'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    public function store(Request $request, int $assetId)
    {
        $asset = FieldAsset::findOrFail($assetId);
        $data = $request->validate([
            'doc_type' => ['required', Rule::in(FieldAssetDocument::TYPES)],
            'label' => 'nullable|string|max:255',
            'expires_on' => 'nullable|date',
            'work_order_id' => ['nullable', Rule::exists('field_asset_work_orders', 'id')->where('field_asset_id', $asset->id)],
            'compliance_id' => ['nullable', Rule::exists('field_asset_compliance', 'id')->where('field_asset_id', $asset->id)],
            'inspection_id' => ['nullable', Rule::exists('field_asset_inspections', 'id')->where('field_asset_id', $asset->id)],
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|max:25600|mimes:'.self::MIMES,
        ]);

        $saved = [];
        foreach ($request->file('files') as $file) {
            $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'dat');
            $saved[] = FieldAssetDocument::create([
                'field_asset_id' => $asset->id,
                'work_order_id' => $data['work_order_id'] ?? null,
                'compliance_id' => $data['compliance_id'] ?? null,
                'inspection_id' => $data['inspection_id'] ?? null,
                'doc_type' => $data['doc_type'],
                'label' => $data['label'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $file->storeAs("field_assets/{$asset->id}", $name, 'local'),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return response()->json($saved, 201);
    }

    public function download(int $assetId, int $documentId)
    {
        $doc = FieldAssetDocument::where('field_asset_id', $assetId)->findOrFail($documentId);
        if (! Storage::disk('local')->exists($doc->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($doc->file_path, $doc->original_name);
    }

    /** Soft-deletes the row; the file stays on disk. */
    public function destroy(int $assetId, int $documentId)
    {
        FieldAssetDocument::where('field_asset_id', $assetId)->findOrFail($documentId)->delete();

        return response()->json(['message' => 'Document deleted']);
    }
}
