<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguratorPdfTemplate;
use App\Models\DoorFrameConfiguration;
use App\Services\Configurator\PackageReportService;
use Illuminate\Http\Request;

class PackageReportController extends Controller
{
    /** Jobs -> work orders that have configurations, for the picker. */
    public function sources()
    {
        $configs = DoorFrameConfiguration::with(['businessJob', 'workOrder'])->notArchived()->get();

        $jobs = $configs->groupBy('business_job_id')->map(fn ($group) => [
            'id' => $group->first()->business_job_id,
            'name' => $group->first()->businessJob?->job_name ?: $group->first()->businessJob?->job_number,
            'work_orders' => $group->groupBy(fn ($c) => $c->work_order_id ?? 0)->map(fn ($wo) => [
                'id' => $wo->first()->work_order_id,
                'name' => $wo->first()->workOrder?->release_token ?? 'No work order',
                'configuration_ids' => $wo->pluck('id')->values(),
                'count' => $wo->count(),
            ])->values(),
        ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return response()->json(['jobs' => $jobs]);
    }

    public function show(Request $request, PackageReportService $service)
    {
        $data = $request->validate([
            'configuration_ids' => 'required|array|min:1',
            'configuration_ids.*' => 'integer|exists:door_frame_configurations,id',
            'sections' => 'nullable|array',
        ]);

        $configs = DoorFrameConfiguration::whereIn('id', $data['configuration_ids'])->get();
        $sections = collect($data['sections'] ?? [])->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))->all();

        return response()->json($service->build($configs, $sections));
    }

    public function cutListCsv(Request $request, PackageReportService $service)
    {
        $data = $request->validate([
            'configuration_ids' => 'required|array|min:1',
            'configuration_ids.*' => 'integer|exists:door_frame_configurations,id',
            'sections' => 'nullable|array',
        ]);

        $configs = DoorFrameConfiguration::whereIn('id', $data['configuration_ids'])->get();
        $sections = collect($data['sections'] ?? [])->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))->all();
        $rows = $service->cutListCsvRows($configs, $sections);

        $job = $configs->first()?->businessJob?->job_number ?? 'job';
        $filename = 'CutList_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $job).'.csv';

        return response()->stream(function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    public function templates()
    {
        return response()->json([
            'blocks' => PackageReportService::BLOCKS,
            'layouts' => [
                'door' => ConfiguratorPdfTemplate::layoutFor('door'),
                'frame' => ConfiguratorPdfTemplate::layoutFor('frame'),
            ],
        ]);
    }

    public function updateTemplate(Request $request, string $type)
    {
        abort_unless(in_array($type, ['door', 'frame'], true), 404);

        $keys = array_keys(PackageReportService::BLOCKS[$type]);
        $data = $request->validate([
            'layout' => 'required|array|min:1',
            'layout.*.key' => 'required|in:'.implode(',', $keys),
            'layout.*.span' => 'required|integer|min:1|max:12',
            'layout.*.enabled' => 'required|boolean',
            'layout.*.cols' => 'nullable|array',
            'layout.*.cols.*' => 'boolean',
        ]);

        ConfiguratorPdfTemplate::updateOrCreate(['report_type' => $type], ['layout' => $data['layout']]);

        return response()->json(['message' => 'Template saved', 'layout' => $data['layout']]);
    }
}
