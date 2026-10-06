<?php

namespace App\Models\CutFlow;

use App\Models\FdWorkOrder;
use Illuminate\Database\Eloquent\Model;

class CutJob extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'name',
        'work_order_id',
        'bom_diverged_at',
    ];

    protected $casts = ['bom_diverged_at' => 'datetime'];

    /**
     * Name to print on labels: the ForgeDesk job's name when this cut job came
     * from a work order, else the cut job's own name (CSV imports).
     */
    public function labelJobName(): string
    {
        $name = $this->work_order_id
            ? FdWorkOrder::with('businessJob')->find($this->work_order_id)?->businessJob?->job_name
            : null;

        return trim((string) $name) ?: (string) $this->name;
    }

    public function parts()
    {
        return $this->hasMany(Part::class);
    }
}
