<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'pre_check_done', 'shipped', 'delivered', 'delivered_issues', 'cancelled'];

    protected $fillable = [
        'shipment_number', 'business_job_id', 'status', 'ship_date', 'destination', 'carrier', 'notes',
        'created_by', 'shipped_at',
    ];

    protected $casts = ['ship_date' => 'date:Y-m-d', 'shipped_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
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

    /** "Job name (number)" label shown to crews and in lists. */
    public function jobLabel(): ?string
    {
        if (! $this->job) {
            return null;
        }

        return trim(($this->job->job_name ?? '').' '.($this->job->job_number ? '('.$this->job->job_number.')' : ''));
    }
}
