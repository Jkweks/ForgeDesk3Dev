<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittalSample extends Model
{
    protected $fillable = ['submittal_id', 'description', 'manufacturer', 'status', 'returned_at', 'notes'];

    protected $casts = ['returned_at' => 'date'];
}
