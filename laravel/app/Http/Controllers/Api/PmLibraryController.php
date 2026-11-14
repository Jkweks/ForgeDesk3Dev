<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmFile;
use App\Models\PmManufacturer;
use App\Models\PmProduct;
use App\Models\PmProductCategory;
use App\Models\PmProductDocument;
use App\Services\Pm\PmFileIngestor;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Product library for submittals: manufacturers, categories, products and their spec/color sheets. */
class PmLibraryController extends Controller
{
    // ---- lookups ----

    public function lookups()
    {
        return response()->json([
            'manufacturers' => PmManufacturer::withCount('products')->orderBy('name')->get(),
            'categories' => PmProductCategory::withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeManufacturer(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:pm_manufacturers,name', 'website' => 'nullable|string|max:255', 'notes' => 'nullable|string']);

        return response()->json(PmManufacturer::create($data), 201);
    }

    public function updateManufacturer(Request $request, int $id)
    {
        $m = PmManufacturer::findOrFail($id);
        $m->update($request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('pm_manufacturers', 'name')->ignore($m->id)], 'website' => 'nullable|string|max:255', 'notes' => 'nullable|string']));

        return response()->json($m);
    }

    public function destroyManufacturer(int $id)
    {
        $m = PmManufacturer::withCount('products')->findOrFail($id);
        if ($m->products_count > 0) {
            return response()->json(['message' => 'This manufacturer still has products. Move or delete them first.'], 422);
        }
        $m->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:pm_product_categories,name', 'sort_order' => 'nullable|integer|min:0']);

        return response()->json(PmProductCategory::create($data), 201);
    }

    public function destroyCategory(int $id)
    {
        PmProductCategory::findOrFail($id)->delete(); // products keep existing, uncategorised

        return response()->json(['message' => 'Deleted']);
    }

    // ---- products ----

    public function products(Request $request)
    {
        $q = PmProduct::with(['manufacturer:id,name', 'category:id,name'])->withCount('documents');
        if ($request->filled('manufacturer_id')) {
            $q->where('pm_manufacturer_id', $request->manufacturer_id);
        }
        if ($request->filled('category_id')) {
            $q->where('pm_product_category_id', $request->category_id);
        }
        if (! $request->boolean('include_inactive')) {
            $q->where('is_active', true);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('model', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%"));
        }

        return response()->json($q->orderBy('name')->limit(1000)->get());
    }

    public function showProduct(int $id)
    {
        return response()->json(PmProduct::with(['manufacturer', 'category', 'documents'])->findOrFail($id));
    }

    private function productRules(): array
    {
        return [
            'pm_manufacturer_id' => 'required|exists:pm_manufacturers,id',
            'pm_product_category_id' => 'nullable|exists:pm_product_categories,id',
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function storeProduct(Request $request)
    {
        $p = PmProduct::create($request->validate($this->productRules()));

        return response()->json($p->load(['manufacturer', 'category', 'documents']), 201);
    }

    public function updateProduct(Request $request, int $id)
    {
        $p = PmProduct::findOrFail($id);
        $p->update($request->validate($this->productRules()));

        return response()->json($p->load(['manufacturer', 'category', 'documents']));
    }

    public function destroyProduct(int $id)
    {
        PmProduct::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    // ---- documents ----

    public function addDocument(Request $request, int $id, PmFileIngestor $files)
    {
        $p = PmProduct::findOrFail($id);
        $data = $request->validate([
            'file' => 'required|file|max:51200',
            'kind' => ['required', Rule::in(PmProductDocument::KINDS)],
            'label' => 'nullable|string|max:255',
        ]);
        [$file] = $files->registerUpload($request->file('file'), 'product_document', null, $request->user());
        $p->documents()->create(['pm_file_id' => $file->id, 'kind' => $data['kind'], 'label' => $data['label'] ?? null]);

        return response()->json($p->load(['manufacturer', 'category', 'documents']), 201);
    }

    public function removeDocument(int $id, int $docId)
    {
        $p = PmProduct::findOrFail($id);
        PmProductDocument::where('pm_product_id', $p->id)->findOrFail($docId)->delete(); // the file record stays (hash-deduped)

        return response()->json($p->load(['manufacturer', 'category', 'documents']));
    }

    /** Download a registered file. Job files follow the job scope; library files are visible to anyone with PM access. */
    public function download(Request $request, int $fileId, PmFileIngestor $files): StreamedResponse
    {
        $file = PmFile::findOrFail($fileId);
        if ($file->business_job_id) {
            PmJobScope::find($request->user(), $file->business_job_id); // 404 outside your scope
        } elseif ($file->doc_type !== 'product_document') {
            // Files with no job and no library role (e.g. unplaced files in the inbox) are for people who manage the inbox.
            abort_unless($request->user()->hasPermission('pm.jobs.manage'), 404);
        }
        $bytes = $files->bytes($file);

        return response()->streamDownload(fn () => print ($bytes), $file->original_name);
    }
}
