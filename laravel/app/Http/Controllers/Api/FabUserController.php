<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FdUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class FabUserController extends Controller
{
    public function index(Request $request)
    {
        $query = FdUser::orderBy('name');

        if (! $request->boolean('all')) {
            $query->where('active', true);
        }

        $users = $query->get(['id', 'name', 'initials', 'role', 'email', 'active', 'fab_pin', 'fab_pin_enc'])
            ->map(fn ($u) => array_merge($u->makeHidden(['fab_pin', 'fab_pin_enc'])->toArray(), [
                'has_pin' => ! is_null($u->fab_pin),
                // Only PINs set since the encrypted copy was added can be shown (see showPin()).
                'pin_viewable' => ! is_null($u->fab_pin_enc),
            ]));

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

    public function setPin(Request $request, int $id)
    {
        $request->validate(['pin' => 'nullable|digits_between:4,8']);
        $user = FdUser::findOrFail($id);
        // Sign-in checks the bcrypt hash; the encrypted copy is only there so an admin can look the PIN up.
        $user->fab_pin = $request->pin ? Hash::make($request->pin) : null;
        $user->fab_pin_enc = $request->pin ? Crypt::encryptString((string) $request->pin) : null;
        $user->save();

        return response()->json(['updated' => $id]);
    }

    /** Reveal a fab user's PIN to an admin. Null when it was set before the encrypted copy existed (set it again). */
    public function showPin(Request $request, int $id)
    {
        $user = FdUser::findOrFail($id);
        $pin = null;
        if ($user->fab_pin_enc) {
            try {
                $pin = Crypt::decryptString($user->fab_pin_enc);
            } catch (\Throwable) {
                $pin = null;   // e.g. the app key changed
            }
        }
        Log::info('Fab user PIN viewed', ['fd_user_id' => $user->id, 'by' => $request->user()?->id]);

        return response()->json(['pin' => $pin, 'has_pin' => ! is_null($user->fab_pin)]);
    }
}
