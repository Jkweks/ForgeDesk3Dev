<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FabricationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FabricationDocumentController extends Controller
{
    /**
     * List documents with optional filtering and sorting.
     */
    public function index(Request $request)
    {
        $query = FabricationDocument::query();

        // Type filter
        if ($request->filled('type') && in_array($request->type, ['fabrication', 'installation', 'maintenance'])) {
            $query->where('type', $request->type);
        }

        // Hardware type filter
        if ($request->filled('hwtype')) {
            $query->where('hwtype', $request->hwtype);
        }

        // Manufacturer filter
        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->manufacturer);
        }

        // Text search (title, hwtype, manufacturer, notes)
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('hwtype', 'like', "%{$q}%")
                    ->orWhere('manufacturer', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'alpha'  => $query->orderBy('title', 'asc'),
            default  => $query->orderBy('created_at', 'desc'),
        };

        // Return all or paginated
        if ($request->get('per_page') === 'all') {
            $docs = $query->get();
            return response()->json([
                'data' => $docs->map(fn($d) => $this->formatDoc($d)),
            ]);
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn($d) => $this->formatDoc($d)),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new document (with optional file upload).
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:fabrication,installation,maintenance',
            'hwtype'       => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:5000',
            'tags'         => 'nullable|string',
            'file'         => 'nullable|file|max:51200', // 50 MB max
        ]);

        $data = [
            'title'        => $request->title,
            'type'         => $request->type,
            'hwtype'       => $request->hwtype,
            'manufacturer' => $request->manufacturer,
            'notes'        => $request->notes,
            'tags'         => $this->parseTags($request->tags),
            'created_by'   => $request->user()?->id,
        ];

        if ($request->hasFile('file')) {
            $fileData = $this->storeFile($request->file('file'));
            $data = array_merge($data, $fileData);
        }

        $doc = FabricationDocument::create($data);

        return response()->json($this->formatDoc($doc), 201);
    }

    /**
     * Show a single document.
     */
    public function show(FabricationDocument $fabricationDocument)
    {
        return response()->json($this->formatDoc($fabricationDocument));
    }

    /**
     * Update a document (with optional file replacement).
     */
    public function update(Request $request, FabricationDocument $fabricationDocument)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:fabrication,installation,maintenance',
            'hwtype'       => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:5000',
            'tags'         => 'nullable|string',
            'file'         => 'nullable|file|max:51200',
            'remove_file'  => 'nullable|boolean',
        ]);

        $data = [
            'title'        => $request->title,
            'type'         => $request->type,
            'hwtype'       => $request->hwtype,
            'manufacturer' => $request->manufacturer,
            'notes'        => $request->notes,
            'tags'         => $this->parseTags($request->tags),
        ];

        if ($request->hasFile('file')) {
            // Delete old file if present
            if ($fabricationDocument->file_path) {
                Storage::disk('public')->delete($fabricationDocument->file_path);
            }
            $fileData = $this->storeFile($request->file('file'));
            $data = array_merge($data, $fileData);
        } elseif ($request->boolean('remove_file') && $fabricationDocument->file_path) {
            Storage::disk('public')->delete($fabricationDocument->file_path);
            $data['file_name'] = null;
            $data['file_path'] = null;
            $data['file_size'] = null;
            $data['file_mime'] = null;
        }

        $fabricationDocument->update($data);

        return response()->json($this->formatDoc($fabricationDocument->fresh()));
    }

    /**
     * Delete a document (soft delete; file stays on disk until force-deleted).
     */
    public function destroy(FabricationDocument $fabricationDocument)
    {
        // Also delete the physical file immediately on soft-delete
        if ($fabricationDocument->file_path) {
            Storage::disk('public')->delete($fabricationDocument->file_path);
            $fabricationDocument->update([
                'file_name' => null,
                'file_path' => null,
                'file_size' => null,
                'file_mime' => null,
            ]);
        }

        $fabricationDocument->delete();

        return response()->json(['message' => 'Document deleted'], 200);
    }

    /**
     * Return distinct values used for filter dropdowns.
     */
    public function filterOptions()
    {
        $hwtypes = FabricationDocument::whereNotNull('hwtype')
            ->distinct()
            ->orderBy('hwtype')
            ->pluck('hwtype');

        $manufacturers = FabricationDocument::whereNotNull('manufacturer')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer');

        return response()->json([
            'hwtypes'       => $hwtypes,
            'manufacturers' => $manufacturers,
        ]);
    }

    // -------------------------------------------------------------------------

    private function storeFile(\Illuminate\Http\UploadedFile $file): array
    {
        $slug    = Str::uuid()->toString();
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("fabrication-documents/{$slug}", $safeName, 'public');

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_mime' => $file->getMimeType(),
        ];
    }

    private function parseTags(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        // Accept JSON array string or comma-separated
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function formatDoc(FabricationDocument $doc): array
    {
        return [
            'id'           => $doc->id,
            'title'        => $doc->title,
            'type'         => $doc->type,
            'hwtype'       => $doc->hwtype,
            'manufacturer' => $doc->manufacturer,
            'tags'         => $doc->tags ?? [],
            'notes'        => $doc->notes,
            'file'         => $doc->file,
            'created_at'   => $doc->created_at?->toIso8601String(),
            'updated_at'   => $doc->updated_at?->toIso8601String(),
            'created_by'   => $doc->created_by,
        ];
    }
}
