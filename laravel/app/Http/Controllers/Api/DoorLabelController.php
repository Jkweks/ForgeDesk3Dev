<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoorFrameConfiguration;
use App\Services\Configurator\DoorLabelService;
use Illuminate\Http\Request;

class DoorLabelController extends Controller
{
    /**
     * Doors that can be labelled, grouped job -> work order, for the picker.
     */
    public function sources()
    {
        $configs = DoorFrameConfiguration::with(['businessJob', 'workOrder', 'doors', 'openingSpecs'])
            ->whereIn('job_scope', ['door_and_frame', 'door_only'])
            ->whereHas('doorConfigs')
            ->get();

        $jobs = $configs->groupBy('business_job_id')->map(function ($group) {
            $job = $group->first()->businessJob;

            return [
                'id' => $job?->id,
                'name' => $job?->job_name ?: $job?->job_number,
                'work_orders' => $group->groupBy(fn ($c) => $c->work_order_id ?? 0)->map(fn ($wo) => [
                    'id' => $wo->first()->work_order_id,
                    'name' => $wo->first()->workOrder?->release_token ?? 'No work order',
                    'configurations' => $wo->map(fn ($c) => [
                        'id' => $c->id,
                        'tags' => $c->doors->pluck('door_tag')->implode(' / ') ?: "Config #{$c->id}",
                        'handing' => $c->openingSpecs?->deriveDoorHanding(),
                        'status' => $c->status,
                    ])->sortBy('tags', SORT_NATURAL | SORT_FLAG_CASE)->values(),
                ])->values(),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json(['jobs' => $jobs]);
    }

    /**
     * Label data for the given configurations (renderer-agnostic — see DoorLabelService).
     */
    public function index(Request $request, DoorLabelService $service)
    {
        $data = $request->validate([
            'configuration_ids' => 'required|array|min:1',
            'configuration_ids.*' => 'integer|exists:door_frame_configurations,id',
        ]);

        $configs = DoorFrameConfiguration::whereIn('id', $data['configuration_ids'])->get();

        return response()->json([
            'labels' => $service->build($configs),
        ]);
    }
}
