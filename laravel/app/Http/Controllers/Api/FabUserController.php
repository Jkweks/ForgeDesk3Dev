<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdUser;
use Illuminate\Http\Request;

class FabUserController extends Controller
{
    public function index(Request $request)
    {
        $query = FdUser::orderBy('name');

        if (!$request->boolean('all')) {
            $query->where('active', true);
        }

        $users = $query->get(['id', 'name', 'initials', 'role', 'email', 'active']);

        return response()->json(['users' => $users]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:worker,manager,admin',
        ]);

        $user = FdUser::create($request->only(['name', 'initials', 'role', 'email', 'active']));

        return response()->json(['id' => $user->id], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = FdUser::findOrFail($id);
        $user->fill($request->only(['name', 'initials', 'role', 'email', 'active']));
        $user->save();

        return response()->json(['updated' => $id]);
    }

    public function destroy(int $id)
    {
        $user = FdUser::findOrFail($id);
        $user->active = false;
        $user->save();

        return response()->json(['deactivated' => $id]);
    }
}
