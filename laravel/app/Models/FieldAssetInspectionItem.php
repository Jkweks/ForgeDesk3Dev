<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldAssetInspectionItem extends Model
{
    protected $fillable = ['inspection_id', 'label', 'critical', 'result', 'comment'];

    protected $casts = ['critical' => 'boolean'];
}
