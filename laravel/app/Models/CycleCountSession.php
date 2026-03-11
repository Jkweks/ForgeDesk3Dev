<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CycleCountSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'session_number',
        'location',
        'storage_location_ids',
        'category_id',
        'status',
        'scheduled_date',
        'started_at',
        'completed_at',
        'assigned_to',
        'reviewed_by',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'storage_location_ids' => 'array',
    ];

    protected $appends = [
        'total_items',
        'counted_items',
        'variance_items',
        'accuracy_percentage',
        'progress_percentage',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items()
    {
        return $this->hasMany(CycleCountItem::class, 'session_id');
    }

    // Computed properties
    public function getTotalItemsAttribute()
    {
        return $this->items()->count();
    }

    public function getCountedItemsAttribute()
    {
        return $this->items()->whereNotNull('counted_quantity')->count();
    }

    public function getVarianceItemsAttribute()
    {
        return $this->items()->where('variance', '!=', 0)->count();
    }

    public function getTotalVarianceAttribute()
    {
        return $this->items()->sum('variance');
    }

    public function getAccuracyPercentageAttribute()
    {
        $total = $this->total_items;
        if ($total == 0) return 100;
        $accurate = $this->items()->where('variance', 0)->count();
        return round(($accurate / $total) * 100, 1);
    }

    public function getProgressPercentageAttribute()
    {
        $total = $this->total_items;
        if ($total == 0) return 0;
        return round(($this->counted_items / $total) * 100, 1);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['planned', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('location', $location);
    }

    // Generate next session number
    public static function generateSessionNumber()
    {
        $date = date('Ymd');
        $lastSession = self::where('session_number', 'like', "CC-{$date}-%")
            ->orderBy('session_number', 'desc')
            ->first();

        if ($lastSession) {
            $lastNum = intval(substr($lastSession->session_number, -3));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return "CC-{$date}-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }

    // Start counting
    public function start()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    // Complete counting
    public function complete($reviewerId = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'reviewed_by' => $reviewerId,
        ]);
    }
}
