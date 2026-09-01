<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeNewUserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Get all users with optional filtering
     */
    public function index(Request $request)
    {
        $query = User::query()->with('roleModel');

        // Filter by role
        if ($request->has('role') && $request->role !== '') {
            $query->where('role', $request->role);
        }

        // Filter by active status
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active === 'active' || $request->is_active === '1');
        }

        // Search by name or email
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        // Transform the data
        $users = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
                'role_display_name' => $user->roleModel?->display_name ?? ucfirst($user->role),
                'is_active' => $user->is_active,
                'status' => $user->is_active ? 'active' : 'inactive',
                'must_change_password' => $user->must_change_password,
                'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
                'temp_password_expired' => $user->temporaryPasswordExpired(),
                'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($users);
    }

    /**
     * Get statistics about users
     */
    public function statistics()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'admin_users' => User::where('role', 'admin')->count(),
            'by_role' => User::selectRaw('role, count(*) as count')
                ->groupBy('role')
                ->get()
                ->pluck('count', 'role'),
        ];

        return response()->json($stats);
    }

    /**
     * Get a single user
     */
    public function show($id)
    {
        $user = User::with('roleModel')->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->full_name ?: $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role,
            'role_display_name' => $user->roleModel?->display_name ?? ucfirst($user->role),
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
            'temp_password_expired' => $user->temporaryPasswordExpired(),
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => ['required', Rule::exists('roles', 'name')],
            'is_active' => 'sometimes|boolean',
        ]);

        // Only an admin may create another admin.
        if ($validated['role'] === 'admin' && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can assign the admin role'], 403);
        }

        // Generate full name from first and last name
        $validated['name'] = trim("{$validated['first_name']} {$validated['last_name']}");

        // The admin never picks the password. We issue a random temporary one,
        // email it to the user, and require a change within the configured window.
        $temporaryPassword = Str::password((int) config('auth.temp_password.length', 16));
        $validated['password'] = $temporaryPassword;
        $validated['must_change_password'] = true;
        $validated['password_set_at'] = now();

        $user = User::create($validated);

        $emailSent = $this->sendWelcomeEmail($user, $temporaryPassword);

        return response()->json([
            'message' => $emailSent
                ? 'User created. A welcome email with a temporary password has been sent.'
                : 'User created, but the welcome email could not be sent. Use "Resend invitation" to try again.',
            'email_sent' => $emailSent,
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Re-issue a temporary password and resend the welcome email.
     *
     * Used when the original invitation expired (past the 48h window) or was lost.
     */
    public function resendInvitation(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (! $user->is_active) {
            return response()->json(['message' => 'Reactivate the account before resending an invitation.'], 422);
        }

        $temporaryPassword = $user->issueTemporaryPassword();

        // A fresh temporary password invalidates any active sessions/tokens.
        $user->tokens()->delete();

        $emailSent = $this->sendWelcomeEmail($user, $temporaryPassword);

        return response()->json([
            'message' => $emailSent
                ? 'Invitation resent with a new temporary password.'
                : 'A new temporary password was set, but the email could not be sent.',
            'email_sent' => $emailSent,
            'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
        ]);
    }

    /**
     * Deliver the welcome / temporary-password email. Never throws — a mail
     * failure must not roll back user creation.
     */
    private function sendWelcomeEmail(User $user, string $temporaryPassword): bool
    {
        try {
            $user->notify(new WelcomeNewUserNotification($temporaryPassword));

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome email to ' . $user->email . ': ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|nullable|string|min:8',
            'role' => ['sometimes', 'required', Rule::exists('roles', 'name')],
            'is_active' => 'sometimes|boolean',
        ]);

        // Only an admin may grant the admin role, or change an existing admin's role.
        if (isset($validated['role']) && $validated['role'] !== $user->role
            && ($validated['role'] === 'admin' || $user->role === 'admin')
            && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can assign or remove the admin role'], 403);
        }

        // Don't let a user deactivate or demote their own account and lock themselves out.
        if ($user->id === $request->user()->id) {
            if (array_key_exists('is_active', $validated) && ! $validated['is_active']) {
                return response()->json(['message' => 'You cannot deactivate your own account'], 403);
            }
            if (isset($validated['role']) && $validated['role'] !== $user->role) {
                return response()->json(['message' => 'You cannot change your own role'], 403);
            }
        }

        // Update full name if first_name or last_name changed
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $firstName = $validated['first_name'] ?? $user->first_name;
            $lastName = $validated['last_name'] ?? $user->last_name;
            $validated['name'] = trim("{$firstName} {$lastName}");
        }

        // Remove password from update if not provided
        if (isset($validated['password']) && empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Delete a user (soft delete)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Restore a soft-deleted user
     */
    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'message' => 'User restored successfully',
            'user' => $user,
        ]);
    }

    /**
     * Reset password for a user (admin function)
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // An admin-chosen password is temporary by nature — force the user to
        // replace it on next sign-in, and restart the change window.
        $user->update([
            'password' => $validated['password'],
            'must_change_password' => true,
            'password_set_at' => now(),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. The user must set a new password on next sign-in.'
        ]);
    }

    /**
     * Send password reset link to user
     */
    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to email'
            ]);
        }

        return response()->json([
            'message' => 'Unable to send password reset link'
        ], 500);
    }

    /**
     * Change own password (for logged-in user)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']]
            ], 422);
        }

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
            'password_set_at' => now(),
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Update own profile (for logged-in user)
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        // Update full name if first_name or last_name changed
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $firstName = $validated['first_name'] ?? $user->first_name;
            $lastName = $validated['last_name'] ?? $user->last_name;
            $validated['name'] = trim("{$firstName} {$lastName}");
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
