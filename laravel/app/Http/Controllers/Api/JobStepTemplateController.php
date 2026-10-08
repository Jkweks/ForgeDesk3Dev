<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdJobStepTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin-managed list of job-level steps. New work orders get a copy of the active ones, in order; work orders
 * that already exist keep the steps they were created with.
 */
class JobStepTemplateController extends Controller
{
    public function index()
    {
        return response()->json(['job_step_templates' => FdJobStepTemplate::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);

        $template = FdJobStepTemplate::create([
            'name' => trim($data['name']),
            'sort_order' => (FdJobStepTemplate::max('sort_order') ?? 0) + 1,
            'active' => true,
        ]);

        return response()->json(['job_step_template' => $template], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'active' => 'sometimes|boolean',
        ]);

        $template = FdJobStepTemplate::findOrFail($id);
        $template->fill(array_filter([
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'active' => $data['active'] ?? null,
        ], fn ($v) => $v !== null))->save();

        return response()->json(['job_step_template' => $template]);
    }

    /** Body: { order: [id, id, ...] } — the full list in its new order. */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer|exists:fd_job_step_templates,id',
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['order']) as $i => $id) {
                FdJobStepTemplate::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return $this->index();
    }

    public function destroy(int $id)
    {
        FdJobStepTemplate::findOrFail($id)->delete();

        return response()->json(['deleted' => $id]);
    }
}
