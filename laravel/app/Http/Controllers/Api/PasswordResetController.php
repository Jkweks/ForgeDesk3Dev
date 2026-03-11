<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link to user's email
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // For security, don't reveal if email exists
            return response()->json([
                'message' => 'If an account exists with this email, you will receive a password reset link shortly.'
            ], 200);
        }

        // Check if user account is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.'
            ], 403);
        }

        // Delete any existing reset tokens for this user
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Generate reset token
        $token = Str::random(64);

        // Store reset token in database
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Send reset email
        try {
            $user->notify(new PasswordResetNotification($token));
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email: ' . $e->getMessage());
            // Continue anyway to not reveal if email exists
        }

        return response()->json([
            'message' => 'If an account exists with this email, you will receive a password reset link shortly.'
        ], 200);
    }

    /**
     * Reset user password using token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Find reset token
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            throw ValidationException::withMessages([
                'email' => ['Invalid or expired password reset token.'],
            ]);
        }

        // Check if token is expired (60 minutes)
        $expiresAt = now()->subMinutes(config('auth.passwords.users.expire', 60));
        if ($resetRecord->created_at < $expiresAt) {
            // Delete expired token
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            throw ValidationException::withMessages([
                'email' => ['Password reset token has expired. Please request a new one.'],
            ]);
        }

        // Verify token
        if (!Hash::check($request->token, $resetRecord->token)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid password reset token.'],
            ]);
        }

        // Find user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.'
            ], 403);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete reset token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password has been reset successfully. You can now login with your new password.'
        ], 200);
    }

    /**
     * Verify if a reset token is valid (optional endpoint for frontend validation)
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired token.',
            ], 200);
        }

        // Check if expired
        $expiresAt = now()->subMinutes(config('auth.passwords.users.expire', 60));
        if ($resetRecord->created_at < $expiresAt) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'valid' => false,
                'message' => 'Token has expired.',
            ], 200);
        }

        // Verify token
        if (!Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid token.',
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token is valid.',
        ], 200);
    }
}
