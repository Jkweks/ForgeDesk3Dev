<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmChangeLogField extends Model
{
    public $timestamps = false;

    protected $fillable = ['pm_change_log_id', 'field', 'old_value', 'new_value'];
}
