<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'type', 'quantity', 'quantity_before', 'quantity_after',
        'reference_number', 'reference_type', 'reference_id', 'business_job_id', 'notes',
        'user_id', 'transaction_date',
    ];

    protected $appends = ['user_display_name'];

    protected $casts = [
        'transaction_date' => 'datetime',
        'quantity' => 'decimal:1',
        'quantity_before' => 'decimal:1',
        'quantity_after' => 'decimal:1',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who the log shows: the user, or "CutFlow" for stock the cut station consumed (those rows carry no
     * user — the operator's name is in the notes instead). Null for other system-written rows.
     */
    public function getUserDisplayNameAttribute(): ?string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return str_starts_with((string) $this->notes, \App\Services\CutFlow\CutConsumptionService::NOTES_PREFIX) ? 'CutFlow' : null;
    }

    public function businessJob()
    {
        return $this->belongsTo(\App\Models\BusinessJob::class, 'business_job_id');
    }
}
