<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittalItem extends Model
{
    protected $fillable = ['submittal_id', 'pm_file_id', 'pm_product_document_id', 'label', 'sort_order'];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }
}
