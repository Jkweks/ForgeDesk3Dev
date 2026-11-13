<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CutlistRuleSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];
}
