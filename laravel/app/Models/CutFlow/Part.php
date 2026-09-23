<?php

namespace App\Models\CutFlow;

use App\Models\Product;
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

    /**
     * `name`/`finish` are how CutFlow spells a raw extrusion SKU — set from
     * $product->part_number/finish on export (see CutFlowExportService)
     * — but Part lives on the separate 'cutflow' connection, so there's no
     * real FK to join on. This is a best-effort lookup by that same
     * part_number+finish pair, used only to show the operator a reference
     * photo of the profile they're about to cut.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $product = Product::where('part_number', $this->name)
            ->where('finish', $this->finish)
            ->whereNotNull('photo_path')
            ->first();

        return $product?->photo_url;
    }
}
