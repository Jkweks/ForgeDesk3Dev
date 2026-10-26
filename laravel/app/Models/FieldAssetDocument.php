<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldAssetDocument extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'title', 'registration', 'insurance', 'manual', 'warranty', 'invoice', 'receipt',
        'inspection_report', 'certification', 'photo', 'other',
    ];

    protected $fillable = [
        'field_asset_id', 'work_order_id', 'compliance_id', 'inspection_id', 'doc_type', 'label',
        'original_name', 'file_path', 'file_size', 'file_mime', 'expires_on', 'uploaded_by',
    ];

    protected $casts = ['expires_on' => 'date:Y-m-d'];

    protected $hidden = ['file_path'];

    public function asset()
    {
        return $this->belongsTo(FieldAsset::class, 'field_asset_id');
    }
}
