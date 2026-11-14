<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittalResponse extends Model
{
    protected $fillable = ['submittal_id', 'revision_number', 'pm_file_id', 'status', 'returned_at', 'responded_by', 'notes', 'created_by'];

    protected $casts = ['returned_at' => 'date'];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }
}
