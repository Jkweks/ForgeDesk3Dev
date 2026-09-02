<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Company-wide settings singleton (branding, etc.). Always accessed through
 * {@see CompanySetting::current()} — there is exactly one row.
 */
class CompanySetting extends Model
{
    protected $fillable = ['logo_path'];

    protected $appends = ['logo_url'];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? url('storage/'.$this->logo_path) : null;
    }
}
