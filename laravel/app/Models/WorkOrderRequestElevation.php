<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One elevation of a drawing set released by a work order request, and how many of it. */
class WorkOrderRequestElevation extends Model
{
    protected $fillable = ['work_order_request_id', 'drawing_elevation_id', 'fd_wo_elevation_id', 'label', 'quantity', 'total_reqd', 'ro_w', 'ro_h', 'system', 'finish', 'arch_ref'];

    public function request()
    {
        return $this->belongsTo(WorkOrderRequest::class, 'work_order_request_id');
    }

    /** "W9 x 2 of 4 - R.O. 60" x 84", TUBELITE (CLEAR)": the line that goes into the work order's notes. */
    public function summary(): string
    {
        $in = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.').'"';
        $size = $this->ro_w !== null && $this->ro_h !== null ? 'R.O. '.$in($this->ro_w).' x '.$in($this->ro_h) : null;
        $what = trim(($this->system ?: '').($this->finish ? ' ('.$this->finish.')' : ''));

        return "{$this->label} x {$this->quantity}".($this->total_reqd ? " of {$this->total_reqd}" : '')
            .collect([$size, $what ?: null, $this->arch_ref ? "[{$this->arch_ref}]" : null])->filter()->map(fn ($x) => ' - '.$x)->implode('');
    }
}
