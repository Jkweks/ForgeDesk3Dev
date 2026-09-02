<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One of the buying company's own addresses. Exactly one row is flagged
 * `is_primary` — that one is the order-from / bill-to block on a purchase order;
 * any location can be selected as a PO's ship-to address.
 */
class CompanyLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'is_primary',
        'address_line1', 'address_line2', 'city', 'state', 'zip', 'country',
        'phone', 'fax', 'email', 'notes', 'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['address_block'];

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /** The primary location, falling back to the first one that exists. */
    public static function primaryLocation(): ?self
    {
        return static::query()->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->first();
    }

    /** Human-readable multi-line address (newline-separated). */
    public function getAddressBlockAttribute(): string
    {
        $cityLine = trim(implode(', ', array_filter([
            $this->city,
            trim(implode(' ', array_filter([$this->state, $this->zip]))),
        ])));

        return implode("\n", array_filter([
            $this->address_line1,
            $this->address_line2,
            $cityLine,
            $this->country && strtoupper($this->country) !== 'USA' ? $this->country : null,
        ]));
    }
}
