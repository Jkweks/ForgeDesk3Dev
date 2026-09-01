<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'must_change_password',
        'password_set_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_set_at' => 'datetime',
        ];
    }

    /**
     * When the current temporary password stops working.
     *
     * Returns null when the user is not on a temporary password.
     */
    public function passwordExpiresAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->must_change_password || ! $this->password_set_at) {
            return null;
        }

        return $this->password_set_at->copy()->addHours(
            (int) config('auth.temp_password.ttl_hours', 48)
        );
    }

    /**
     * True when the user still owes a password change and the 48h window has passed.
     */
    public function temporaryPasswordExpired(): bool
    {
        $expiresAt = $this->passwordExpiresAt();

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * Issue a fresh temporary password and (re)start the change-within-48h clock.
     * Returns the plaintext password so the caller can email it.
     */
    public function issueTemporaryPassword(): string
    {
        $plain = \Illuminate\Support\Str::password(
            (int) config('auth.temp_password.length', 16)
        );

        $this->forceFill([
            'password' => $plain,
            'must_change_password' => true,
            'password_set_at' => now(),
        ])->save();

        return $plain;
    }

    /**
     * Get the full name attribute
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the role relationship
     */
    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    /**
     * Check if user has a specific permission
     *
     * The `admin` role is always granted every permission. This is a fail-safe:
     * new permissions are added by migrations that must remember to grant them to
     * admin, and a single missed grant would otherwise silently lock admins out
     * with no way to recover through the UI.
     */
    public function hasPermission($permission)
    {
        if ($this->role === 'admin') {
            return true;
        }

        if (!$this->roleModel) {
            return false;
        }

        return $this->roleModel->permissions()->where('name', $permission)->exists();
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions)
    {
        if ($this->role === 'admin') {
            return true;
        }

        if (!$this->roleModel) {
            return false;
        }

        return $this->roleModel->permissions()->whereIn('name', $permissions)->exists();
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin()
    {
        $this->last_login_at = now();
        $this->save();
    }

    /**
     * Scope to filter active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by role
     */
    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }
}

