<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StickItem extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'stick_session_id',
        'part_id',
        'dimension_inches',
        'sequence',
        'status',
        'pending_uuid',
        'label_printed_at',
    ];

    protected $casts = [
        'dimension_inches' => 'decimal:3',
        'label_printed_at' => 'datetime',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function stickSession()
    {
        return $this->belongsTo(StickSession::class);
    }

    public function isLabelPrinted(): bool
    {
        return $this->label_printed_at !== null;
    }

    /**
     * The uuid a label printed for this item (before its CutLogEntry
     * exists) carries in its QR — reserved once and reused on reprints so
     * every early-printed sticker for this item points at the same eventual
     * record. Persisted (not just held in Livewire memory) so it survives a
     * page reload between printing and the cut being confirmed.
     */
    public function reservePendingUuid(): string
    {
        if (! $this->pending_uuid) {
            $this->pending_uuid = (string) Str::uuid();
            $this->save();
        }

        return $this->pending_uuid;
    }
}
