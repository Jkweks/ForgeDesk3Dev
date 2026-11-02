<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\Shipment;
use App\Models\ShipmentInspectionPhoto;
use App\Services\Shipping\ShipmentInspectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Shipments and their pre-shipment inspection. Post-delivery inspections arrive from the
 * field app through App\Services\Fleet\FieldSync, not through here.
 */
class ShipmentController extends Controller
{
    public function __construct(private ShipmentInspectionService $inspections) {}

    public function index(Request $request)
    {
        $q = Shipment::with(['job:id,job_number,job_name', 'loads.type:id,name'])
            ->withCount('inspections')->orderByDesc('id');
        if ($request->filled('status')) {
            $q->whereIn('status', array_filter(explode(',', (string) $request->query('status'))));
        }
        if ($request->filled('job_id')) {
            $q->where('business_job_id', (int) $request->query('job_id'));
        }
        if ($request->filled('search')) {
            $s = '%'.$request->query('search').'%';
            $q->where(fn ($w) => $w->where('shipment_number', 'like', $s)->orWhere('destination', 'like', $s)
                ->orWhereHas('job', fn ($j) => $j->where('job_number', 'like', $s)->orWhere('job_name', 'like', $s)));
        }

        return response()->json($q->limit(500)->get());
    }

    /**
     * Shipping queue window: everything dated inside [from, to], plus the open shipments that need
     * attention regardless of the window (past-due and not-yet-scheduled drafts).
     */
    public function queue(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = Carbon::parse($request->query('from', now()->startOfWeek()))->toDateString();
        $to = Carbon::parse($request->query('to', Carbon::parse($from)->addDays(13)))->toDateString();
        $open = ['draft', 'pre_check_done'];

        $rows = Shipment::with(['job:id,job_number,job_name', 'loads.type:id,name', 'inspections:id,shipment_id,phase,result'])
            ->where('status', '!=', 'cancelled')
            ->where(fn ($w) => $w->whereBetween('ship_date', [$from, $to])
                ->orWhere(fn ($x) => $x->whereNull('ship_date')->whereIn('status', $open))
                ->orWhere(fn ($x) => $x->where('ship_date', '<', $from)->whereIn('status', $open)))
            ->orderBy('ship_date')->orderBy('shipment_number')->get();

        return response()->json(['from' => $from, 'to' => $to, 'shipments' => $rows->map(fn (Shipment $r) => [
            'id' => $r->id, 'shipment_number' => $r->shipment_number, 'status' => $r->status,
            'ship_date' => $r->ship_date?->format('Y-m-d'), 'destination' => $r->destination, 'carrier' => $r->carrier,
            'job' => $r->job, 'loads' => $r->loads,
            'pre_result' => $r->inspections->firstWhere('phase', 'pre')?->result,
            'post_result' => $r->inspections->firstWhere('phase', 'post')?->result,
            'movable' => in_array($r->status, $open, true),
        ])->values()]);
    }

    /** Drag-to-reschedule from the queue. Only shipments that haven't left can move. */
    public function schedule(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if (! in_array($shipment->status, ['draft', 'pre_check_done'], true)) {
            return response()->json(['message' => 'A shipment that has shipped cannot be rescheduled.'], 422);
        }
        $data = $request->validate(['ship_date' => 'present|nullable|date']);
        $shipment->update(['ship_date' => $data['ship_date']]);

        return response()->json($shipment->fresh(['job:id,job_number,job_name', 'loads.type:id,name']));
    }

    public function jobOptions(Request $request)
    {
        $q = BusinessJob::query()->whereNotIn('status', ['completed', 'cancelled'])->orderByDesc('id');
        if ($request->filled('search')) {
            $s = '%'.$request->query('search').'%';
            $q->where(fn ($w) => $w->where('job_number', 'like', $s)->orWhere('job_name', 'like', $s));
        }

        return response()->json($q->limit(30)->get(['id', 'job_number', 'job_name']));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);

        $shipment = DB::transaction(function () use ($data, $request) {
            $s = Shipment::create([
                'shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'created_by' => $request->user()->id,
            ] + collect($data)->except('loads')->all());
            $s->loads()->createMany($data['loads']);

            return $s;
        });

        return response()->json($this->detail($shipment), 201);
    }

    public function show(int $id)
    {
        return response()->json($this->detail(Shipment::findOrFail($id)));
    }

    public function update(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $data = $this->validated($request, false);

        DB::transaction(function () use ($shipment, $data) {
            $shipment->update(collect($data)->except('loads')->all());
            if (isset($data['loads'])) {
                $keep = collect($data['loads'])->pluck('shipment_type_id')->all();
                $shipment->loads()->whereNotIn('shipment_type_id', $keep)->delete();
                foreach ($data['loads'] as $l) {
                    $shipment->loads()->updateOrCreate(['shipment_type_id' => $l['shipment_type_id']], $l);
                }
            }
        });

        return response()->json($this->detail($shipment->fresh()));
    }

    public function destroy(int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if (in_array($shipment->status, ['shipped', 'delivered', 'delivered_issues'], true)) {
            return response()->json(['message' => 'A shipped shipment cannot be deleted. Cancel it instead.'], 422);
        }
        $shipment->delete();

        return response()->json(['message' => 'Shipment deleted']);
    }

    public function cancel(int $id)
    {
        Shipment::findOrFail($id)->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Shipment cancelled']);
    }

    /** Mark the truck as gone: this is what publishes it to the field app for the post-delivery check. */
    public function ship(int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if ($shipment->status !== 'pre_check_done') {
            return response()->json(['message' => 'Complete a passing pre-shipment inspection before shipping.'], 422);
        }
        $shipment->update(['status' => 'shipped', 'shipped_at' => now(), 'ship_date' => $shipment->ship_date ?? now()->toDateString()]);

        return response()->json($this->detail($shipment->fresh()));
    }

    /**
     * Multipart so phone photos can ride along: `answers` is JSON {itemId: {value, comment, photos:[n]}}
     * and `photos[]` are the image files those indexes refer to.
     */
    public function preInspection(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if (! in_array($shipment->status, ['draft', 'pre_check_done'], true)) {
            return response()->json(['message' => 'This shipment has already shipped.'], 422);
        }
        $request->validate([
            'answers' => 'required|string', 'notes' => 'nullable|string|max:5000',
            'photos' => 'nullable|array|max:'.ShipmentInspectionService::MAX_PHOTOS,
            'photos.*' => 'file|max:15360|mimes:jpg,jpeg,png,webp',
        ]);
        $answers = json_decode($request->input('answers'), true);
        if (! is_array($answers)) {
            return response()->json(['message' => 'Answers are not valid JSON.'], 422);
        }
        $photos = collect($request->file('photos', []))->map(fn ($f) => [
            'bytes' => (string) file_get_contents($f->getRealPath()), 'name' => $f->getClientOriginalName(),
        ])->all();

        $inspection = $this->inspections->record($shipment, 'pre', $answers, $photos, [
            'user_id' => $request->user()->id, 'notes' => $request->input('notes'), 'source' => 'office',
        ]);

        return response()->json($this->detail($shipment->fresh()) + ['inspection_id' => $inspection->id], 201);
    }

    public function photo(int $shipmentId, int $photoId)
    {
        $photo = ShipmentInspectionPhoto::whereHas('inspection', fn ($q) => $q->where('shipment_id', $shipmentId))->findOrFail($photoId);
        if (! Storage::disk('local')->exists($photo->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('local')->response($photo->file_path, $photo->original_name);
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'business_job_id' => ['nullable', Rule::exists('business_jobs', 'id')],
            'ship_date' => 'nullable|date', 'destination' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:5000',
            'loads' => "$req|array|min:1|max:20",
            'loads.*.shipment_type_id' => ['required', 'distinct', Rule::exists('shipment_types', 'id')->where('is_active', true)],
            'loads.*.description' => 'nullable|string|max:255', 'loads.*.piece_count' => 'nullable|integer|min:0|max:100000',
        ]);
    }

    private function detail(Shipment $shipment): array
    {
        $shipment->load(['job:id,job_number,job_name', 'loads.type:id,name', 'creator:id,name',
            'inspections.items.photos', 'inspections.photos', 'inspections.performer:id,name']);

        return $shipment->toArray() + [
            'pre_checklist' => $this->inspections->checklist($shipment, 'pre'),
            'post_checklist' => $this->inspections->checklist($shipment, 'post'),
        ];
    }
}
