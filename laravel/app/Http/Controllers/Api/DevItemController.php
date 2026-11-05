<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin-only (route middleware `admin`) development tracker. */
class DevItemController extends Controller
{
    private function filtered(Request $request)
    {
        $q = DevItem::with('creator:id,name');

        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('priority')) {
            $q->where('priority', $request->priority);
        }
        // status=active means anything not closed out
        if ($request->status === 'active') {
            $q->whereIn('status', ['open', 'in_progress']);
        } elseif ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = '%'.mb_strtolower($request->search).'%';
            $q->where(fn ($w) => $w->whereRaw('lower(title) like ?', [$s])->orWhereRaw('lower(description) like ?', [$s]));
        }

        return $q;
    }

    public function index(Request $request)
    {
        $items = $this->filtered($request)->latest('id')->limit(500)->get();

        return response()->json([
            'data' => $items,
            'counts' => [
                'open' => DevItem::where('status', 'open')->count(),
                'in_progress' => DevItem::where('status', 'in_progress')->count(),
                'resolved' => DevItem::where('status', 'resolved')->count(),
                'bugs_active' => DevItem::where('type', 'bug')->whereIn('status', ['open', 'in_progress'])->count(),
                'features_active' => DevItem::where('type', 'feature')->whereIn('status', ['open', 'in_progress'])->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(DevItem::TYPES)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'console_output' => 'nullable|string|max:50000',
            'page_url' => 'nullable|string|max:500',
            'priority' => ['nullable', Rule::in(DevItem::PRIORITIES)],
        ]);

        $item = DevItem::create($data + [
            'priority' => $data['priority'] ?? 'normal',
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($item->load('creator:id,name'), 201);
    }

    public function show(DevItem $devItem)
    {
        return response()->json($devItem->load('creator:id,name'));
    }

    public function update(Request $request, DevItem $devItem)
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(DevItem::TYPES)],
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'console_output' => 'nullable|string|max:50000',
            'priority' => ['sometimes', Rule::in(DevItem::PRIORITIES)],
            'status' => ['sometimes', Rule::in(DevItem::STATUSES)],
            'resolution_notes' => 'nullable|string|max:20000',
        ]);

        if (isset($data['status'])) {
            $closed = in_array($data['status'], ['resolved', 'wont_fix'], true);
            $data['resolved_at'] = $closed ? ($devItem->resolved_at ?? now()) : null;
        }

        $devItem->update($data);

        return response()->json($devItem->load('creator:id,name'));
    }

    public function destroy(DevItem $devItem)
    {
        $devItem->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /** Markdown dump of the (filtered) list, ready to paste to Claude as the fix queue. */
    public function export(Request $request)
    {
        $request->mergeIfMissing(['status' => 'active']);
        $items = $this->filtered($request)->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->orderBy('id')->get();

        $md = "# ForgeDesk development items ({$items->count()})\n\n".$items->map->toMarkdown()->implode("\n---\n\n");

        return response($md, 200, ['Content-Type' => 'text/markdown; charset=utf-8']);
    }
}
