<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingAxisCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['drawing_elevation_id', 'axis', 'ok', 'member', 'used', 'dropped', 'chains', 'printed', 'edited',
        'resolution', 'resolution_reason', 'resolved_by', 'resolved_at'];

    protected $casts = ['ok' => 'boolean', 'printed' => 'boolean', 'edited' => 'boolean', 'resolved_at' => 'datetime'];

    protected $appends = ['state'];

    /**
     * closes      the frame closes on the readings
     * corrected   a person changed a value and it now closes
     * flagged     it does not close and nobody has dealt with it (blocks approval)
     * unverified  does not close; kept, marked unverified by a person
     * accepted    does not close; accepted as read/entered with a reason
     * unverifiable could not be judged (no frame size, nothing in it, or a multi-sheet run)
     */
    public function getStateAttribute(): string
    {
        if ($this->ok === null) {
            return 'unverifiable';
        }
        if ($this->ok) {
            return $this->edited ? 'corrected' : 'closes';
        }

        return match ($this->resolution) {
            'accepted' => 'accepted',
            'unverified' => 'unverified',
            default => 'flagged',
        };
    }
}
