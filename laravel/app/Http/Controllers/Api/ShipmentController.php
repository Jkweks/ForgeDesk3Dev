<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessJob;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\ShipmentInspectionPhoto;
use App\Models\ShipmentLocation;
use App\Services\ImageSanitizer;
use App\Services\Shipping\ShipmentInspectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shipments and their pre-shipment inspection. Post-delivery inspections arrive from the
 * field app through App\Services\Fleet\FieldSync, not through here.
 */
class ShipmentController extends Controller
{
    public function __construct(private ShipmentInspectionService $inspections) {}

    public function index(Request $request)
    {
        $q = Shipment::with(['jobs:id,job_number,job_name,site_address', 'loads.type:id,name', 'elevations.workOrder.businessJob:id,job_number'])
            ->withCount(['inspections', 'elevations'])->orderByDesc('id');
        if ($request->filled('status')) {
            $q->whereIn('status', array_filter(explode(',', (string) $request->query('status'))));
        }
        if ($request->filled('job_id')) {
            $q->whereHas('jobs', fn ($j) => $j->where('business_jobs.id', (int) $request->query('job_id')));
        }
        if ($request->filled('search')) {
            $s = '%'.$request->query('search').'%';
            $q->where(fn ($w) => $w->where('shipment_number', 'like', $s)->orWhere('destination', 'like', $s)
                ->orWhereRaw('CAST(custom_jobs AS TEXT) like ?', [$s])
                ->orWhereHas('jobs', fn ($j) => $j->where('job_number', 'like', $s)->orWhere('job_name', 'like', $s)));
        }

        $rows = $q->limit(500)->get();
        $trips = app(\App\Services\Shipping\WorkOrderShipmentService::class)->tripLabels($rows);

        return response()->json($rows->map(function (Shipment $r) use ($trips) {
            // One chip per work order on the load: its release label, the elevations of it on this shipment, and their tags.
            $workOrders = $r->elevations->groupBy('work_order_id')->map(function ($els) {
                $wo = $els->first()->workOrder;

                return [
                    'id' => (int) $els->first()->work_order_id,
                    'label' => $wo ? $wo->releaseLabel() : 'WO #'.$els->first()->work_order_id,
                    'elevations' => $els->count(),
                    'tags' => $els->map(fn ($e) => $e->elevation_tag ?: '#'.$e->id)->values()->all(),
                    'partial' => $els->contains(fn ($e) => (bool) $e->pivot->partial),
                ];
            })->values()->all();
            $arr = $r->toArray();
            unset($arr['elevations']);

            return $arr + ['trip' => $trips[$r->id] ?? null, 'work_orders' => $workOrders];
        }));
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

        $rows = Shipment::with(['jobs:id,job_number,job_name,site_address', 'loads.type:id,name', 'inspections:id,shipment_id,phase,result'])
            ->where('status', '!=', 'cancelled')
            ->where(fn ($w) => $w->whereBetween('ship_date', [$from, $to])
                ->orWhere(fn ($x) => $x->whereNull('ship_date')->whereIn('status', $open))
                ->orWhere(fn ($x) => $x->where('ship_date', '<', $from)->whereIn('status', $open)))
            ->orderBy('ship_date')->orderBy('shipment_number')->get();

        $trips = app(\App\Services\Shipping\WorkOrderShipmentService::class)->tripLabels($rows);

        return response()->json(['from' => $from, 'to' => $to, 'shipments' => $rows->map(fn (Shipment $r) => [
            'trip' => $trips[$r->id] ?? null, 'id' => $r->id, 'shipment_number' => $r->shipment_number, 'status' => $r->status,
            'ship_date' => $r->ship_date?->format('Y-m-d'), 'destination' => $r->destination, 'carrier' => $r->carrier,
            'jobs' => $r->jobs, 'custom_jobs' => $r->custom_jobs ?? [], 'loads' => $r->loads,
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

        return response()->json($shipment->fresh(['jobs:id,job_number,job_name,site_address', 'loads.type:id,name']));
    }

    public function jobOptions(Request $request)
    {
        $q = BusinessJob::query()->whereNotIn('status', ['completed', 'cancelled'])->orderByDesc('id');
        if ($request->filled('id')) {
            $q->where('id', (int) $request->query('id'));
        }
        if ($request->filled('search')) {
            $s = '%'.mb_strtolower($request->query('search')).'%';
            $q->where(fn ($w) => $w->whereRaw('LOWER(job_number) like ?', [$s])->orWhereRaw('LOWER(job_name) like ?', [$s]));
        }

        return response()->json($q->limit(30)->get(['id', 'job_number', 'job_name', 'site_address']));
    }

    public function locations()
    {
        return response()->json(ShipmentLocation::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get());
    }

    /**
     * Address lookup for the jobsite / custom drop-off. Proxied through the server (OpenStreetMap Nominatim) so
     * the browser never talks to a third party, results are cached, and the usage policy's User-Agent is honoured.
     */
    public function addressSearch(Request $request)
    {
        $q = trim((string) $request->validate(['q' => 'required|string|min:3|max:200'])['q']);
        $results = Cache::remember('nominatim2:'.md5(mb_strtolower($q)), now()->addDay(), function () use ($q) {
            $res = Http::withUserAgent('ForgeDesk/1.0 (shipping address lookup)')->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q, 'format' => 'jsonv2', 'limit' => 6, 'countrycodes' => 'us,ca',
                ]);
            if (! $res->ok()) {
                return null;   // not cached
            }

            return collect($res->json())->map(fn ($r) => [
                'label' => $r['display_name'], 'name' => $r['name'] ?? '', 'lat' => round((float) $r['lat'], 7), 'lng' => round((float) $r['lon'], 7),
            ])->values()->all();
        });
        if ($results === null) {
            Cache::forget('nominatim2:'.md5(mb_strtolower($q)));

            return response()->json(['message' => 'Address search is unavailable right now. Try again in a moment.'], 503);
        }

        return response()->json($results);
    }

    /** "Other" picks are remembered under their business name so they're in the list next time. */
    private function saveVisited(string $p, array $data): ShipmentLocation
    {
        $name = trim((string) ($data["{$p}_new_name"] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(["{$p}_new_name" => 'Enter the business name to save this location under.']);
        }
        $existing = ShipmentLocation::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            if (trim((string) $existing->address) === trim((string) $data["{$p}_address"])) {
                return $existing;
            }
            throw ValidationException::withMessages(["{$p}_new_name" => "A location called \"{$existing->name}\" is already saved with a different address. Pick it from the list or use another name."]);
        }

        return ShipmentLocation::create([
            'name' => $name, 'kind' => 'both', 'requires_address' => false, 'saves_visits' => false,
            'address' => $data["{$p}_address"], 'lat' => $data["{$p}_lat"] ?? null, 'lng' => $data["{$p}_lng"] ?? null,
            'sort_order' => 500,
        ]);
    }

    /** A jobsite address searched for on a shipment is written back to the job (only if the job has none and the user may edit jobs). */
    private function saveJobSite(Request $request, array $data): void
    {
        $jobId = (int) ($data['save_site_to_job_id'] ?? 0);
        if (! $jobId || blank($data['dropoff_address'] ?? null) || ! in_array($jobId, $data['job_ids'] ?? [], true)
            || ! $request->user()->hasPermission('jobs.edit')) {
            return;
        }
        $job = BusinessJob::find($jobId);
        if ($job && blank($job->site_address)) {
            $job->update(['site_address' => Str::limit($data['dropoff_address'], 500, '')]);
        }
    }

    /** Validates the pickup / drop-off picks and snapshots each one's address onto the shipment. */
    private function withLocations(array $data, bool $creating): array
    {
        foreach (['pickup' => ['pickup', 'both'], 'dropoff' => ['dropoff', 'both']] as $p => $kinds) {
            if (! array_key_exists("{$p}_location_id", $data)) {
                continue;
            }
            $loc = $data["{$p}_location_id"] ? ShipmentLocation::find($data["{$p}_location_id"]) : null;
            if (! $loc) {
                if ($creating) {
                    throw ValidationException::withMessages(["{$p}_location_id" => 'Choose a '.($p === 'pickup' ? 'pickup' : 'drop-off').' location.']);
                }
                foreach (['address', 'lat', 'lng'] as $f) {
                    $data["{$p}_{$f}"] = null;
                }
                continue;
            }
            if (! in_array($loc->kind, $kinds, true)) {
                throw ValidationException::withMessages(["{$p}_location_id" => "{$loc->name} can't be used as a ".($p === 'pickup' ? 'pickup' : 'drop-off').' location.']);
            }
            if ($loc->requires_address) {
                if (blank($data["{$p}_address"] ?? null)) {
                    throw ValidationException::withMessages(["{$p}_address" => "Search for and pick the {$loc->name} address."]);
                }
                if ($loc->saves_visits) {
                    $saved = $this->saveVisited($p, $data);
                    $data["{$p}_location_id"] = $saved->id;
                }
            } else {
                $data["{$p}_address"] = $loc->address;
                $data["{$p}_lat"] = $loc->lat;
                $data["{$p}_lng"] = $loc->lng;
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->withLocations($this->validated($request, true), true);
        $this->saveJobSite($request, $data);

        $shipment = DB::transaction(function () use ($data, $request) {
            $s = Shipment::create([
                'shipment_number' => Shipment::nextNumber(), 'status' => 'draft', 'created_by' => $request->user()->id,
            ] + collect($data)->except('loads', 'job_ids', 'pickup_new_name', 'dropoff_new_name', 'save_site_to_job_id')->all());
            $s->jobs()->sync($data['job_ids'] ?? []);
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
        $data = $this->withLocations($this->validated($request, false), false);
        $this->saveJobSite($request, $data);

        DB::transaction(function () use ($shipment, $data) {
            $before = $shipment->loads()->pluck('shipment_type_id')->sort()->values()->all();
            $shipment->update(collect($data)->except('loads', 'job_ids', 'pickup_new_name', 'dropoff_new_name', 'save_site_to_job_id')->all());
            if (array_key_exists('job_ids', $data)) {
                $shipment->jobs()->sync($data['job_ids'] ?? []);
            }
            if (isset($data['loads'])) {
                $keep = collect($data['loads'])->pluck('shipment_type_id')->all();
                $shipment->loads()->whereNotIn('shipment_type_id', $keep)->delete();
                foreach ($data['loads'] as $l) {
                    $shipment->loads()->updateOrCreate(['shipment_type_id' => $l['shipment_type_id']], $l);
                }
                // The pre-check was done against a different checklist: it has to be redone.
                if ($shipment->status === 'pre_check_done' && $keep !== [] && collect($keep)->sort()->values()->all() !== $before) {
                    $shipment->update(['status' => 'draft']);
                }
            }
        });

        return response()->json($this->detail($shipment->fresh()));
    }

    public function destroy(int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if (in_array($shipment->status, ['shipped', 'awaiting_verification', 'delivered', 'delivered_issues'], true)) {
            return response()->json(['message' => 'A shipped shipment cannot be deleted. Cancel it instead.'], 422);
        }
        $shipment->delete();

        return response()->json(['message' => 'Shipment deleted']);
    }

    public function cancel(Request $request, int $id)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        $shipment = Shipment::findOrFail($id);
        if (in_array($shipment->status, ['awaiting_verification', 'delivered', 'delivered_issues', 'cancelled'], true)) {
            return response()->json(['message' => 'This shipment can no longer be cancelled.'], 422);
        }
        $shipment->update([
            'status' => 'cancelled', 'cancel_reason' => trim($data['reason']),
            'cancelled_at' => now(), 'cancelled_by' => $request->user()->id,
        ]);

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
        return $this->recordInspection($request, $id, 'pre');
    }

    /**
     * Office-entered post-delivery inspection (the field app is the usual route, via FieldSync): same
     * checklist, photos and outcome handling, so a delivery marked undamaged/damaged here behaves identically.
     */
    public function postInspection(Request $request, int $id)
    {
        return $this->recordInspection($request, $id, 'post');
    }

    private function recordInspection(Request $request, int $id, string $phase)
    {
        $shipment = Shipment::findOrFail($id);
        if ($phase === 'pre' && ! in_array($shipment->status, ['draft', 'pre_check_done'], true)) {
            return response()->json(['message' => 'This shipment has already shipped.'], 422);
        }
        if ($phase === 'post' && $shipment->status !== 'shipped') {
            return response()->json(['message' => $shipment->inspections()->where('phase', 'post')->exists()
                ? 'A delivery inspection was already recorded for this shipment.'
                : 'Only a shipment that has shipped can be inspected on delivery.'], 422);
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

        $inspection = $this->inspections->record($shipment, $phase, $answers, $photos, [
            'user_id' => $request->user()->id, 'notes' => $request->input('notes'), 'source' => 'office',
        ]);

        return response()->json($this->detail($shipment->fresh()) + ['inspection_id' => $inspection->id], 201);
    }

    /** Office sign-off on a received delivery: closes the shipment as delivered (or delivered with issues). */
    public function verify(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if ($shipment->status !== 'awaiting_verification') {
            return response()->json(['message' => 'Only a received delivery that is awaiting verification can be verified.'], 422);
        }
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $this->inspections->verify($shipment, $request->user()->id, $data['notes'] ?? null);

        return response()->json($this->detail($shipment->fresh()));
    }

    /** Signed shipping tickets: PDF or image, several at once. Images are re-encoded; PDFs must really be PDFs. */
    public function uploadDocuments(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        if (in_array($shipment->status, ['draft', 'pre_check_done', 'cancelled'], true)) {
            return response()->json(['message' => 'Tickets can be attached once the shipment has shipped.'], 422);
        }
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|max:15360|mimes:pdf,jpg,jpeg,png,webp',
        ]);
        if ($shipment->documents()->count() + count($request->file('files')) > 30) {
            return response()->json(['message' => 'At most 30 documents per shipment.'], 422);
        }

        // Check everything before storing anything, so a bad file does not leave a half-saved batch.
        $prepared = [];
        foreach ($request->file('files') as $f) {
            $bytes = (string) file_get_contents($f->getRealPath());
            if (str_starts_with($bytes, '%PDF-')) {
                $mime = 'application/pdf';
                $ext = 'pdf';
            } elseif ($img = ImageSanitizer::sniff($bytes)) {
                $mime = $img;
                $ext = ImageSanitizer::MIMES[$img];
                $bytes = ImageSanitizer::reencode($bytes, $img);
            } else {
                throw ValidationException::withMessages(['files' => '"'.$f->getClientOriginalName().'" is not a real PDF or image.']);
            }
            $prepared[] = [$bytes, $mime, $ext, Str::limit(basename($f->getClientOriginalName()), 120, '') ?: "ticket.$ext"];
        }
        foreach ($prepared as [$bytes, $mime, $ext, $name]) {
            $path = "shipments/{$shipment->id}/tickets/".Str::uuid().".$ext";
            Storage::disk('local')->put($path, $bytes);
            $shipment->documents()->create([
                'kind' => 'signed_ticket', 'original_name' => $name, 'file_path' => $path, 'file_mime' => $mime,
                'file_size' => strlen($bytes), 'uploaded_by' => $request->user()->id,
            ]);
        }

        return response()->json($this->detail($shipment->fresh()), 201);
    }

    public function document(int $shipmentId, int $docId)
    {
        $doc = ShipmentDocument::where('shipment_id', $shipmentId)->findOrFail($docId);
        if (! Storage::disk('local')->exists($doc->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('local')->response($doc->file_path, $doc->original_name, [
            'Content-Type' => $doc->file_mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteDocument(int $shipmentId, int $docId)
    {
        $shipment = Shipment::findOrFail($shipmentId);
        if (in_array($shipment->status, ['delivered', 'delivered_issues'], true)) {
            return response()->json(['message' => 'This delivery has been verified; its documents can no longer be removed.'], 422);
        }
        $doc = $shipment->documents()->findOrFail($docId);
        Storage::disk('local')->delete($doc->file_path);
        $doc->delete();

        return response()->json($this->detail($shipment->fresh()));
    }

    /** Per-shipment PDF: jobs, loads, every inspection with thumbnails linked to the full-size photos. */
    public function report(int $id)
    {
        $shipment = Shipment::with(['jobs:id,job_number,job_name,site_address', 'loads.type:id,name', 'creator:id,name', 'canceller:id,name', 'pickupLocation:id,name', 'dropoffLocation:id,name',
            'inspections.items.photos', 'inspections.photos', 'inspections.performer:id,name', 'verifier:id,name', 'elevations'])->findOrFail($id);

        $thumbs = [];   // photo id => [data URI, signed full-size URL]
        foreach ($shipment->inspections as $n) {
            foreach ($n->photos as $p) {
                $bytes = Storage::disk('local')->exists($p->file_path) ? Storage::disk('local')->get($p->file_path) : null;
                $thumb = $bytes ? ImageSanitizer::thumbnail($bytes) : null;
                $thumbs[$p->id] = [
                    $thumb ? 'data:image/jpeg;base64,'.base64_encode($thumb) : null,
                    URL::temporarySignedRoute('shipments.photo.signed', now()->addDays(90), ['shipmentId' => $shipment->id, 'photoId' => $p->id]),
                ];
            }
        }

        $pdf = Pdf::loadView('pdfs.shipment-report', [
            'shipment' => $shipment, 'inspections' => $shipment->inspections->sortBy('performed_at')->values(), 'thumbs' => $thumbs,
        ])->setPaper('letter');

        return $pdf->stream($shipment->shipment_number.'-report.pdf');
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
            'job_ids' => 'nullable|array|max:50',
            'job_ids.*' => ['integer', 'distinct', Rule::exists('business_jobs', 'id')],
            'pickup_location_id' => ($creating ? 'required' : 'sometimes').'|nullable|integer|exists:shipment_locations,id',
            'dropoff_location_id' => ($creating ? 'required' : 'sometimes').'|nullable|integer|exists:shipment_locations,id',
            'pickup_new_name' => 'nullable|string|max:120', 'dropoff_new_name' => 'nullable|string|max:120',
            'save_site_to_job_id' => 'nullable|integer',
            'pickup_address' => 'nullable|string|max:500', 'dropoff_address' => 'nullable|string|max:500',
            'pickup_lat' => 'nullable|numeric|between:-90,90', 'pickup_lng' => 'nullable|numeric|between:-180,180',
            'dropoff_lat' => 'nullable|numeric|between:-90,90', 'dropoff_lng' => 'nullable|numeric|between:-180,180',
            'custom_jobs' => 'nullable|array|max:20', 'custom_jobs.*' => 'string|max:120',
            'ship_date' => 'nullable|date', 'destination' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:5000',
            'loads' => "$req|array|min:1|max:20",
            'loads.*.shipment_type_id' => ['required', 'distinct', Rule::exists('shipment_types', 'id')->where('is_active', true)],
            'loads.*.description' => 'nullable|string|max:255', 'loads.*.piece_count' => 'nullable|integer|min:0|max:100000',
        ]);
    }

    private function detail(Shipment $shipment): array
    {
        $shipment->load(['jobs:id,job_number,job_name,site_address', 'loads.type:id,name', 'creator:id,name', 'canceller:id,name', 'pickupLocation:id,name', 'dropoffLocation:id,name',
            'inspections.items.photos', 'inspections.photos', 'inspections.performer:id,name', 'documents.uploader:id,name', 'verifier:id,name']);

        $svc = app(\App\Services\Shipping\WorkOrderShipmentService::class);
        $elevs = $shipment->elevations()->with('elevationType:id,name')->get();

        return $shipment->toArray() + [
            'trip' => $svc->tripLabels(collect([$shipment]))[$shipment->id] ?? null,
            'elevations' => $elevs->map(fn ($e) => ['id' => $e->id, 'tag' => $e->elevation_tag, 'type' => $e->elevationType?->name, 'quantity' => $e->quantity,
                'partial' => (bool) $e->pivot->partial, 'partial_note' => $e->pivot->partial_note])->values(),
            'pre_checklist' => $this->inspections->checklist($shipment, 'pre'),
            'post_checklist' => $this->inspections->checklist($shipment, 'post'),
        ];
    }
}
