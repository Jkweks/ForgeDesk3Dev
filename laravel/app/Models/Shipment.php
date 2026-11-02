<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'pre_check_done', 'shipped', 'delivered', 'delivered_issues', 'cancelled'];

    /** Not yet delivered or cancelled: what the field app is shown. */
    public const OPEN_STATUSES = ['draft', 'pre_check_done', 'shipped'];

    protected $fillable = [
        'shipment_number', 'status', 'ship_date', 'destination', 'carrier', 'notes',
        'created_by', 'shipped_at',
    ];

    protected $casts = ['ship_date' => 'date:Y-m-d', 'shipped_at' => 'datetime'];

    public function jobs()
    {
        return $this->belongsToMany(BusinessJob::class, 'business_job_shipment')->withTimestamps()->orderBy('business_jobs.job_number');
    }

    public function loads()
    {
        return $this->hasMany(ShipmentLoad::class);
    }

    public function inspections()
    {
        return $this->hasMany(ShipmentInspection::class)->orderByDesc('performed_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Shipped but no post-delivery inspection yet: what the field app is shown. */
    public function scopeAwaitingPostCheck($q)
    {
        return $q->where('status', 'shipped');
    }

    public static function nextNumber(): string
    {
        $max = static::withTrashed()->where('shipment_number', 'like', 'SHP-%')->pluck('shipment_number')
            ->map(fn ($n) => (int) substr($n, 4))->max() ?? 0;

        return sprintf('SHP-%05d', $max + 1);
    }

    /** "Job name (number)" labels, comma-joined, shown to crews and in lists. */
    public function jobLabel(): ?string
    {
        $labels = $this->jobs->map(fn ($j) => trim(($j->job_name ?? '').' '.($j->job_number ? '('.$j->job_number.')' : '')))
            ->filter()->values();

        return $labels->isEmpty() ? null : $labels->implode(', ');
    }
}
