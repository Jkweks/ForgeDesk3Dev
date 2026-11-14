<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittalPackage extends Model
{
    protected $fillable = ['submittal_id', 'revision_number', 'pm_file_id', 'item_count', 'page_count', 'created_by'];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }
}
