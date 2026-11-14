<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'entity_type', 'entity_id', 'entity_label', 'business_job_id', 'action'];

    public function fields()
    {
        return $this->hasMany(PmChangeLogField::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
