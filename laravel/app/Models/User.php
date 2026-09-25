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
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
        'welcome_email_sent_at',
        'theme_preferences',
        'wo_column_prefs',
        'jobs_column_prefs',
        'quality_report_prefs',
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
            'welcome_email_sent_at' => 'datetime',
            'theme_preferences' => 'array',
            'wo_column_prefs' => 'array',
            'jobs_column_prefs' => 'array',
            'quality_report_prefs' => 'array',
        ];
    }

    /** Accounts created but not yet emailed their invitation. */
    public function scopePendingWelcome($query)
    {
        return $query->whereNull('welcome_email_sent_at');
    }

    /** "Lastname, Firstname" for people pickers; falls back to name / email. */
    public function getSortNameAttribute(): string
    {
        $last = trim((string) $this->last_name);
        $first = trim((string) $this->first_name);

        if ($last !== '' && $first !== '') {
            return "{$last}, {$first}";
        }

        return $this->name ?: trim("{$first} {$last}") ?: (string) $this->email;
    }

    /**
     * Resolve a person-picker submission to a [id, label] pair. A valid user id
     * wins and sets the label to that user's sort name; otherwise the supplied
     * free-text fallback is kept and the id is null.
     *
     * @return array{id: int|null, label: string|null}
     */
    public static function resolvePersonField($id, ?string $fallbackLabel = null): array
    {
        $user = $id ? static::find($id) : null;

        return [
            'id' => $user?->id,
            'label' => $user?->sort_name ?? ($fallbackLabel !== null && trim($fallbackLabel) !== '' ? trim($fallbackLabel) : null),
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
            (int) config('auth.temp_password.ttl_hours', 168)
        );
    }

    /**
     * True when the user still owes a password change and the TTL window has passed.
     */
    public function temporaryPasswordExpired(): bool
    {
        $expiresAt = $this->passwordExpiresAt();

        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * Issue a fresh temporary password and (re)start the change-within-TTL clock.
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

        if (! $this->roleModel) {
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

        if (! $this->roleModel) {
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

    /**
     * Scope to users who can perform a given permission — either via their
     * role's granted permissions, or because they're an admin (who implicitly
     * has every permission; see hasPermission()).
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->where(function ($q) use ($permission) {
            $q->where('role', 'admin')
                ->orWhereHas('roleModel.permissions', fn ($p) => $p->where('name', $permission));
        });
    }
}
