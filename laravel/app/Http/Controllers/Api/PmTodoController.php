<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmTodo;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;

/**
 * Project Management to-dos. You see a to-do if it is assigned to you, you created it, or its job
 * is in your job scope (everything with pm.jobs.view_all).
 */
class PmTodoController extends Controller
{
    /** @return \Illuminate\Database\Eloquent\Builder */
    public static function visible($user)
    {
        $q = PmTodo::query();
        if (($ids = PmJobScope::visibleIds($user)) !== null) {
            $q->where(fn ($w) => $w->where('assigned_to', $user->id)->orWhere('created_by', $user->id)
                ->orWhereIn('business_job_id', $ids));
        }

        return $q;
    }

    private function find(Request $request, int $id): PmTodo
    {
        return self::visible($request->user())->findOrFail($id);
    }

    private function present(PmTodo $t): array
    {
        return $t->load(['job:id,job_number,job_name', 'assignee:id,name,email'])->toArray();
    }

    public function index(Request $request)
    {
        $q = self::visible($request->user())->with(['job:id,job_number,job_name', 'assignee:id,name,email']);
        if ($request->boolean('mine')) {
            $q->where('assigned_to', $request->user()->id);
        }
        if ($request->filled('business_job_id')) {
            $q->where('business_job_id', $request->business_job_id);
        }
        $request->boolean('done') ? $q->whereNotNull('completed_at') : $q->whereNull('completed_at');

        return response()->json($q->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->limit(500)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255', 'notes' => 'nullable|string', 'due_date' => 'nullable|date',
            'business_job_id' => 'nullable|integer', 'assigned_to' => 'nullable|exists:users,id',
        ]);
        if (! empty($data['business_job_id'])) {
            PmJobScope::find($request->user(), (int) $data['business_job_id']); // 404 outside your scope
        }
        if (! empty($data['assigned_to']) && (int) $data['assigned_to'] !== $request->user()->id && ! $request->user()->hasPermission('pm.act_for_others')) {
            return response()->json(['message' => 'You can only assign to-dos to yourself.'], 403);
        }
        $todo = PmTodo::create($data + ['created_by' => $request->user()->id, 'assigned_to' => $data['assigned_to'] ?? $request->user()->id]);

        return response()->json($this->present($todo), 201);
    }

    public function update(Request $request, int $id)
    {
        $todo = $this->find($request, $id);
        $todo->update($request->validate(['title' => 'sometimes|required|string|max:255', 'notes' => 'nullable|string', 'due_date' => 'nullable|date']));

        return response()->json($this->present($todo));
    }

    public function complete(Request $request, int $id)
    {
        $todo = $this->find($request, $id);
        $todo->update(['completed_at' => now(), 'completed_by' => $request->user()->id]);

        return response()->json($this->present($todo));
    }

    public function reopen(Request $request, int $id)
    {
        $todo = $this->find($request, $id);
        $todo->update(['completed_at' => null, 'completed_by' => null]);

        return response()->json($this->present($todo));
    }

    public function destroy(Request $request, int $id)
    {
        $this->find($request, $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
