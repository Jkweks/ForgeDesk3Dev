<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'cut_job_id',
        'name',
        'finish',
        'dimension_inches',
        'qty_original',
        'qty_remaining',
        'work_order',
        'phase',
        'description',
        'row',
        'column',
        'left_cut_angle',
        'right_cut_angle',
    ];

    protected $casts = [
        'dimension_inches' => 'decimal:3',
        'left_cut_angle' => 'decimal:2',
        'right_cut_angle' => 'decimal:2',
    ];

    public function getProfileLabelAttribute(): string
    {
        return $this->finish ? "{$this->name} · {$this->finish}" : $this->name;
    }

    public function cutJob()
    {
        return $this->belongsTo(CutJob::class);
    }

    public function stickItems()
    {
        return $this->hasMany(StickItem::class);
    }

    public function cutLogEntries()
    {
        return $this->hasMany(CutLogEntry::class);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->qty_remaining <= 0) {
            return 'Done';
        }

        return $this->qty_remaining < $this->qty_original ? 'In progress' : 'Pending';
    }
}
