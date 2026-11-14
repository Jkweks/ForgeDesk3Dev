<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmChangeLog;
use App\Services\Pm\PmJobScope;
use Illuminate\Http\Request;

/** Change history for Project Management records (feature #11). */
class PmHistoryController extends Controller
{
    public function index(Request $request)
    {
        $q = PmChangeLog::with(['fields', 'user:id,name,email'])->orderByDesc('id');

        // Job-bound entries follow the job scope; entries with no job (vendors, library) are visible to everyone with access.
        if (($ids = PmJobScope::visibleIds($request->user())) !== null) {
            $q->where(fn ($w) => $w->whereNull('business_job_id')->orWhereIn('business_job_id', $ids));
        }
        foreach (['entity_type', 'business_job_id', 'user_id', 'action'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('entity_id')) {
            $q->where('entity_id', $request->entity_id);
        }
        if ($request->filled('from')) {
            $q->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->where('created_at', '<=', $request->to.' 23:59:59');
        }
        if ($request->filled('search')) {
            $q->where('entity_label', 'like', '%'.$request->search.'%');
        }

        return response()->json($q->paginate(min((int) $request->get('per_page', 50), 200)));
    }
}
