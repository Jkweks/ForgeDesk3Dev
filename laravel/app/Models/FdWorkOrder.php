<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdWorkOrder extends Model
{
    protected $table = 'fd_work_orders';

    protected $fillable = [
        'business_job_id', 'release_number', 'date_issued',
        'material_delivery', 'notes', 'archived',
    ];

    protected $casts = [
        'date_issued' => 'date',
        'archived'    => 'boolean',
    ];

    public function businessJob(): BelongsTo
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    public function elevations(): HasMany
    {
        return $this->hasMany(FdWoElevation::class, 'work_order_id')->orderBy('created_at');
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(FdWoDrawing::class, 'work_order_id')->orderBy('created_at');
    }

    public function releaseLabel(): string
    {
        $jobNumber = $this->businessJob?->job_number ?? '?';
        return "{$jobNumber}-R{$this->release_number}";
    }

    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }
}
