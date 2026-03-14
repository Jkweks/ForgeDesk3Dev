<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_job_id',
        'reservation_id',
        'job_number',
        'release_number',
        'job_name',
        'requested_by',
        'needed_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'release_number' => 'integer',
    ];

    /**
     * Boot method to add model event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate sequential reservation_id per job when creating
        static::creating(function ($reservation) {
            if (!$reservation->reservation_id && $reservation->business_job_id) {
                // Get the next reservation_id for this job
                $maxReservationId = static::where('business_job_id', $reservation->business_job_id)
                    ->max('reservation_id') ?? 0;
                $reservation->reservation_id = $maxReservationId + 1;
            }
        });

        // When reservation status changes, recalculate committed quantities for all products
        static::updated(function ($reservation) {
            // If status changed, sync all products in this reservation
            if ($reservation->isDirty('status')) {
                $reservation->syncAllProductCommittedQuantities();
            }
        });

        // When reservation is deleted, sync all products
        static::deleted(function ($reservation) {
            $reservation->syncAllProductCommittedQuantities();
        });
    }

    /**
     * Sync committed quantities for all products in this reservation
     */
    public function syncAllProductCommittedQuantities()
    {
        // Get all product IDs from this reservation's items
        $productIds = $this->items()->pluck('product_id')->unique();

        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if (!$product) continue;

            // Calculate total committed from all ACTIVE reservations for this product
            $totalCommitted = JobReservationItem::where('product_id', $productId)
                ->whereHas('reservation', function($query) {
                    $query->whereIn('status', ['active', 'in_progress', 'on_hold'])
                          ->whereNull('deleted_at');
                })
                ->sum('committed_qty');

            $product->quantity_committed = $totalCommitted;
            $product->save();
        }
    }

    /**
     * Get the business job this reservation belongs to
     */
    public function businessJob()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    /**
     * Get the reservation items
     */
    public function items()
    {
        return $this->hasMany(JobReservationItem::class, 'reservation_id');
    }

    /**
     * Get total requested quantity
     */
    public function getTotalRequestedAttribute()
    {
        return $this->items()->sum('requested_qty');
    }

    /**
     * Get total committed quantity
     */
    public function getTotalCommittedAttribute()
    {
        return $this->items()->sum('committed_qty');
    }

    /**
     * Get total consumed quantity
     */
    public function getTotalConsumedAttribute()
    {
        return $this->items()->sum('consumed_qty');
    }

    /**
     * Status labels for display
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Draft - Details still being gathered',
            'active' => 'Active - Inventory committed, waiting to be pulled',
            'in_progress' => 'In Progress - Work started, team consuming inventory',
            'fulfilled' => 'Fulfilled - All inventory reconciled',
            'on_hold' => 'On Hold - Reserved but temporarily paused',
            'cancelled' => 'Cancelled - No inventory being held',
        ];
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabelAttribute()
    {
        $labels = self::statusLabels();
        return $labels[$this->status] ?? $this->status;
    }
}
