<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdScheduleEntry;
use Illuminate\Http\Request;

/** Team-member assists and notes placed on an operator's row of the Work Queue schedule. */
class ScheduleEntryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:fd_users,id',
            'work_order_id' => 'nullable|integer|exists:fd_work_orders,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_quarter' => 'sometimes|integer|between:0,3',
            'end_quarter' => 'sometimes|integer|between:0,3',
            'note' => 'nullable|string|max:255',
        ]);
        if (empty($data['work_order_id']) && blank($data['note'] ?? null)) {
            return response()->json(['message' => 'A note needs some text.'], 422);
        }

        return response()->json(FdScheduleEntry::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $entry = FdScheduleEntry::findOrFail($id);
        $data = $request->validate([
            'user_id' => 'sometimes|integer|exists:fd_users,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'start_quarter' => 'sometimes|integer|between:0,3',
            'end_quarter' => 'sometimes|integer|between:0,3',
            'note' => 'sometimes|nullable|string|max:255',
        ]);
        $entry->fill($data);
        if ($entry->end_date->lt($entry->start_date) || ($entry->end_date->eq($entry->start_date) && $entry->end_quarter < $entry->start_quarter)) {
            return response()->json(['message' => 'End date is before the start date.'], 422);
        }
        $entry->save();

        return response()->json($entry);
    }

    public function destroy(int $id)
    {
        FdScheduleEntry::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
