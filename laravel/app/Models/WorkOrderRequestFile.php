<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderRequestFile extends Model
{
    protected $fillable = ['work_order_request_id', 'pm_file_id', 'label'];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }
}
