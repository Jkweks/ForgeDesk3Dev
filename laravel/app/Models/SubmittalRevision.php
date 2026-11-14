<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittalRevision extends Model
{
    protected $fillable = ['submittal_id', 'revision_number', 'status', 'sent_at', 'returned_at', 'notes', 'created_by'];

    protected $casts = ['sent_at' => 'date', 'returned_at' => 'date'];
}
