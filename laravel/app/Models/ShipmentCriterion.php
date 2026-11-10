<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reusable, standard shipping check. Categories attach it per phase as a ShipmentCheckItem row. */
class ShipmentCriterion extends Model
{
    protected $fillable = ['label', 'input_type', 'unit', 'options', 'required', 'photo_required', 'critical', 'is_active'];

    protected $casts = ['options' => 'array', 'required' => 'boolean', 'photo_required' => 'boolean', 'critical' => 'boolean', 'is_active' => 'boolean'];

    public function checkItems()
    {
        return $this->hasMany(ShipmentCheckItem::class, 'criterion_id');
    }

    /** What a category row copies from the criterion at attach time (flags are then per-category overrides). */
    public function itemDefaults(): array
    {
        return $this->only(['label', 'input_type', 'unit', 'options', 'required', 'photo_required', 'critical']);
    }

    /** Push identity fields (not the per-category flags) to every linked checklist row. */
    public function propagate(): void
    {
        $this->checkItems()->update([
            'label' => $this->label, 'input_type' => $this->input_type, 'unit' => $this->unit,
            'options' => $this->options === null ? null : json_encode($this->options),
        ]);
    }
}
