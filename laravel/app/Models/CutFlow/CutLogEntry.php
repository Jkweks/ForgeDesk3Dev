<?php

namespace App\Models\CutFlow;

use App\Models\FdUser;
use Illuminate\Database\Eloquent\Model;

class CutLogEntry extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'uuid',
        'part_id',
        'part_name',
        'finish',
        'operator_id',
        'operator_name',
        'cut_job_id',
        'job_name',
        'work_order',
        'phase',
        'description',
        'dimension_inches',
        'stick_length_label',
        'stick_session_id',
        'type',
        'is_recut',
        'is_reprint',
    ];

    protected $casts = [
        'dimension_inches' => 'decimal:3',
        'is_recut' => 'boolean',
        'is_reprint' => 'boolean',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * operator_id points at fd_users.id on the default connection — a
     * cross-database reference, not a real FK (Postgres can't FK across
     * databases). Eloquent handles a belongsTo to a model on a different
     * connection fine; operator_name is kept as a snapshot alongside this so
     * history still reads correctly if the FdUser is later renamed/deactivated.
     */
    public function operator()
    {
        return $this->belongsTo(FdUser::class, 'operator_id');
    }

    public function cutJob()
    {
        return $this->belongsTo(CutJob::class);
    }
}
