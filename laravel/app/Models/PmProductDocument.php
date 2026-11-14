<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmProductDocument extends Model
{
    protected $fillable = ['pm_product_id', 'pm_file_id', 'kind', 'label'];

    public const KINDS = ['spec', 'color', 'cut', 'other'];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }
}
