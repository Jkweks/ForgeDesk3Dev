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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            $query->where(function ($q) use ($search) {
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
                'welcome_email_sent_at' => $user->welcome_email_sent_at?->toIso8601String(),
                'invitation_pending' => $user->welcome_email_sent_at === null,
                'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($users);
    }

    /**
     * Lightweight people list for "Requested by" / "Project manager" pickers.
     * Any authenticated user may load this (no users.view needed).
     */
    public function people()
    {
        $people = User::query()
            ->where('is_active', true)
            ->orderByRaw('LOWER(last_name)')
            ->orderByRaw('LOWER(first_name)')
            ->orderBy('name')
            ->get(['id', 'first_name', 'last_name', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->sort_name])
            ->values();

        return response()->json($people);
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
            'pending_invitations' => User::pendingWelcome()->count(),
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
            'welcome_email_sent_at' => $user->welcome_email_sent_at?->toIso8601String(),
            'invitation_pending' => $user->welcome_email_sent_at === null,
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
            // Hold the welcome email so profile / roles / permissions can be set
            // up first, then send it (individually or in bulk) later.
            'send_welcome_email' => 'sometimes|boolean',
        ]);

        $sendWelcome = $request->boolean('send_welcome_email', true);

        // Only an admin may create another admin.
        if ($validated['role'] === 'admin' && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can assign the admin role'], 403);
        }

        // Generate full name from first and last name
        $validated['name'] = trim("{$validated['first_name']} {$validated['last_name']}");

        // The admin never picks the password. We issue a random temporary one and
        // require a change within the configured window. When the welcome email
        // is held, a fresh temporary password is minted at send time (the TTL
        // would otherwise expire before the invite goes out), so the value here
        // is just a placeholder until then.
        $temporaryPassword = Str::password((int) config('auth.temp_password.length', 16));
        $validated['password'] = $temporaryPassword;
        $validated['must_change_password'] = true;
        $validated['password_set_at'] = now();
        unset($validated['send_welcome_email']);

        $user = User::create($validated);

        if (! $sendWelcome) {
            return response()->json([
                'message' => 'User created. The welcome email is held — send it from the Users list once the profile and permissions are set.',
                'email_sent' => false,
                'welcome_held' => true,
                'user' => $this->inviteUserPayload($user),
            ], 201);
        }

        $emailSent = $this->sendWelcomeEmail($user, $temporaryPassword);
        if ($emailSent) {
            $user->forceFill(['welcome_email_sent_at' => now()])->save();
        }

        return response()->json([
            'message' => $emailSent
                ? 'User created. A welcome email with a temporary password has been sent.'
                : 'User created, but the welcome email could not be sent. Use "Send invitation" to try again.',
            'email_sent' => $emailSent,
            'user' => $this->inviteUserPayload($user),
        ], 201);
    }

    /**
     * Issue a fresh temporary password and (re)send the welcome email to one
     * user — the first send for a held invitation, or a resend after the
     * temporary-password window lapsed / the mail was lost.
     */
    public function resendInvitation(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (! $user->is_active) {
            return response()->json(['message' => 'Reactivate the account before sending an invitation.'], 422);
        }

        $firstSend = $user->welcome_email_sent_at === null;
        $emailSent = $this->issueAndSendInvitation($user);

        if (! $emailSent) {
            return response()->json([
                'message' => 'A new temporary password was set, but the email could not be sent.',
                'email_sent' => false,
                'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
            ]);
        }

        return response()->json([
            'message' => $firstSend
                ? 'Invitation sent with a temporary password.'
                : 'Invitation resent with a new temporary password.',
            'email_sent' => true,
            'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
        ]);
    }

    /**
     * Send every held welcome email at once (bulk onboarding). Optionally limit
     * to a subset via `user_ids`. Inactive accounts are skipped.
     */
    public function sendPendingInvitations(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'integer',
        ]);

        $query = User::pendingWelcome();
        if (! empty($data['user_ids'])) {
            $query->whereIn('id', $data['user_ids']);
        }

        $sent = 0;
        $skippedInactive = 0;
        $failed = [];

        foreach ($query->get() as $user) {
            if (! $user->is_active) {
                $skippedInactive++;

                continue;
            }

            if ($this->issueAndSendInvitation($user)) {
                $sent++;
            } else {
                $failed[] = $user->email;
            }
        }

        return response()->json([
            'sent' => $sent,
            'failed' => $failed,
            'skipped_inactive' => $skippedInactive,
            'message' => $sent === 0 && ! $failed && ! $skippedInactive
                ? 'No held invitations to send.'
                : "Sent {$sent} invitation(s)."
                    .($failed ? ' '.count($failed).' failed.' : '')
                    .($skippedInactive ? " {$skippedInactive} skipped (inactive)." : ''),
        ]);
    }

    /**
     * Mint a fresh temporary password, drop any live sessions/tokens, send the
     * welcome mail, and — on success — stamp the invitation as delivered.
     */
    private function issueAndSendInvitation(User $user): bool
    {
        $temporaryPassword = $user->issueTemporaryPassword();
        $user->tokens()->delete();

        if (! $this->sendWelcomeEmail($user, $temporaryPassword)) {
            return false;
        }

        $user->forceFill(['welcome_email_sent_at' => now()])->save();

        return true;
    }

    /** Shared shape for a user in create / invitation responses. */
    private function inviteUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'welcome_email_sent_at' => $user->welcome_email_sent_at?->toIso8601String(),
            'invitation_pending' => $user->welcome_email_sent_at === null,
            'password_expires_at' => optional($user->passwordExpiresAt())->toIso8601String(),
        ];
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
            Log::error('Failed to send welcome email to '.$user->email.': '.$e->getMessage());

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
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
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
            'message' => 'Password reset successfully. The user must set a new password on next sign-in.',
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
                'message' => 'Password reset link sent to email',
            ]);
        }

        return response()->json([
            'message' => 'Unable to send password reset link',
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
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors' => ['current_password' => ['Current password is incorrect']],
            ], 422);
        }

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
            'password_set_at' => now(),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
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

    /**
     * Persist the signed-in user's Tabler theme (color mode, scheme, font,
     * base, radius) so it follows them to any device on next login.
     */
    public function updateThemePreferences(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'sometimes|nullable|in:light,dark',
            'theme-base' => 'sometimes|nullable|in:slate,gray,zinc,neutral,stone',
            'theme-font' => 'sometimes|nullable|in:sans-serif,serif,monospace,comic',
            'theme-primary' => 'sometimes|nullable|in:blue,azure,indigo,purple,pink,red,orange,yellow,lime,green,teal,cyan',
            'theme-radius' => 'sometimes|nullable|in:0,0.5,1,1.5,2',
        ]);

        $user = auth()->user();
        $preferences = array_filter($validated, fn ($value) => $value !== null);

        // The client always sends the full current config, so this is a
        // straight replace, not a merge — a stale partial payload would
        // otherwise silently drop previously saved keys.
        $user->update(['theme_preferences' => $preferences ?: null]);

        return response()->json([
            'theme_preferences' => $user->theme_preferences,
        ]);
    }

    /**
     * Persist the signed-in user's column order + visibility on the Work
     * Orders table, so it follows them to any device on next login.
     *
     * Older clients only ever sent `hidden` (no ordering existed yet), so
     * `wo_column_prefs` may still hold a bare hidden-column array from
     * before this was extended — the frontend treats that as hidden-only
     * with the default order.
     */
    public function updateWoColumnPrefs(Request $request)
    {
        $validated = $request->validate([
            'order' => 'sometimes|array',
            'order.*' => 'string|max:64',
            'hidden' => 'sometimes|array',
            'hidden.*' => 'string|max:64',
        ]);

        $user = auth()->user();
        $order = array_values(array_unique($validated['order'] ?? []));
        $hidden = array_values(array_unique($validated['hidden'] ?? []));

        // The client always sends its full current order + hidden set, so
        // this is a straight replace, not a merge.
        $user->update([
            'wo_column_prefs' => ($order || $hidden) ? ['order' => $order, 'hidden' => $hidden] : null,
        ]);

        return response()->json([
            'wo_column_prefs' => $user->wo_column_prefs,
        ]);
    }

    /**
     * Persist the signed-in user's column order + visibility on the Jobs
     * table, so it follows them to any device on next login.
     */
    public function updateJobsColumnPrefs(Request $request)
    {
        $validated = $request->validate([
            'order' => 'sometimes|array',
            'order.*' => 'string|max:64',
            'hidden' => 'sometimes|array',
            'hidden.*' => 'string|max:64',
        ]);

        $user = auth()->user();
        $order = array_values(array_unique($validated['order'] ?? []));
        $hidden = array_values(array_unique($validated['hidden'] ?? []));

        // The client always sends its full current order + hidden set, so
        // this is a straight replace, not a merge.
        $user->update([
            'jobs_column_prefs' => ($order || $hidden) ? ['order' => $order, 'hidden' => $hidden] : null,
        ]);

        return response()->json([
            'jobs_column_prefs' => $user->jobs_column_prefs,
        ]);
    }

    /**
     * Persist the signed-in user's Quality Reports dashboard settings — which
     * date drives the incident-rate-by-month line, and whether its month-to-
     * date projection is shown — so it follows them to any device on next
     * login.
     */
    public function updateQualityReportPrefs(Request $request)
    {
        $validated = $request->validate([
            'incident_rate_basis' => 'sometimes|nullable|in:report_date,completed_date',
            'incident_rate_show_projection' => 'sometimes|nullable|boolean',
        ]);

        $user = auth()->user();
        $prefs = array_filter($validated, fn ($value) => $value !== null);

        // Merge (not replace) — unlike theme/column prefs, this blob is meant to
        // grow with more report-settings keys over time without each save
        // needing to resend every other setting.
        $user->update(['quality_report_prefs' => array_merge($user->quality_report_prefs ?? [], $prefs)]);

        return response()->json([
            'quality_report_prefs' => $user->quality_report_prefs,
        ]);
    }
}
