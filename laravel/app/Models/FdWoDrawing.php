<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdWoDrawing extends Model
{
    protected $table = 'fd_wo_drawings';

    protected $fillable = [
        'work_order_id', 'original_name', 'file_path', 'file_size', 'file_mime', 'uploaded_by',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
