<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevIssue;
use Illuminate\Http\Request;

class DevIssueController extends Controller
{
    public function index(Request $request)
    {
        $query = DevIssue::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $issues = $query->orderBy('created_at', 'desc')->get();

        return response()->json($issues);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'console_output' => 'nullable|string',
            'page_url'       => 'nullable|string|max:500',
            'user_agent'     => 'nullable|string|max:500',
            'reported_by'    => 'nullable|string|max:255',
            'status'         => 'in:open,in_progress,resolved,wont_fix',
            'priority'       => 'in:low,medium,high,critical',
            'notes'          => 'nullable|string',
        ]);

        $issue = DevIssue::create($validated);

        return response()->json($issue, 201);
    }

    public function show(DevIssue $devIssue)
    {
        return response()->json($devIssue);
    }

    public function update(Request $request, DevIssue $devIssue)
    {
        $validated = $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string',
            'console_output' => 'nullable|string',
            'page_url'       => 'nullable|string|max:500',
            'user_agent'     => 'nullable|string|max:500',
            'reported_by'    => 'nullable|string|max:255',
            'status'         => 'in:open,in_progress,resolved,wont_fix',
            'priority'       => 'in:low,medium,high,critical',
            'notes'          => 'nullable|string',
        ]);

        $devIssue->update($validated);

        return response()->json($devIssue);
    }

    public function destroy(DevIssue $devIssue)
    {
        $devIssue->delete();

        return response()->json(null, 204);
    }
}
