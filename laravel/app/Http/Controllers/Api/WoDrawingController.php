<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdWorkOrder;
use App\Models\FdWoDrawing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WoDrawingController extends Controller
{
    public function index(int $workOrderId)
    {
        FdWorkOrder::findOrFail($workOrderId);
        $drawings = FdWoDrawing::where('work_order_id', $workOrderId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($d) => $this->formatDrawing($d));

        return response()->json(['drawings' => $drawings]);
    }

    public function store(Request $request, int $workOrderId)
    {
        FdWorkOrder::findOrFail($workOrderId);

        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,dwg,dxf,jpg,jpeg,png,xlsx,xls,doc,docx',
        ]);

        try {
            $file         = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $safeName     = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path         = $file->storeAs("work_order_drawings/{$workOrderId}", $safeName, 'local');

            $drawing = FdWoDrawing::create([
                'work_order_id' => $workOrderId,
                'original_name' => $originalName,
                'file_path'     => $path,
                'file_size'     => $file->getSize(),
                'file_mime'     => $file->getMimeType(),
                'uploaded_by'   => $request->user()?->id,
            ]);

            return response()->json($this->formatDrawing($drawing), 201);
        } catch (\Exception $e) {
            Log::error('WoDrawingController@store failed', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Upload failed'], 500);
        }
    }

    public function download(int $workOrderId, int $drawingId)
    {
        $drawing = FdWoDrawing::where('work_order_id', $workOrderId)->findOrFail($drawingId);

        if (!Storage::disk('local')->exists($drawing->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('local')->download($drawing->file_path, $drawing->original_name);
    }

    public function destroy(int $workOrderId, int $drawingId)
    {
        $drawing = FdWoDrawing::where('work_order_id', $workOrderId)->findOrFail($drawingId);

        Storage::disk('local')->delete($drawing->file_path);
        $drawing->delete();

        return response()->json(['deleted' => $drawingId]);
    }

    private function formatDrawing(FdWoDrawing $d): array
    {
        return [
            'id'            => $d->id,
            'work_order_id' => $d->work_order_id,
            'original_name' => $d->original_name,
            'file_size'     => $d->file_size,
            'file_mime'     => $d->file_mime,
            'download_url'  => "/api/v1/work-orders/{$d->work_order_id}/drawings/{$d->id}/download",
            'created_at'    => $d->created_at->toIso8601String(),
        ];
    }
}
