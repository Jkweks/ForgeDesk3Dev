<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibItemDocument extends Model
{
    public const TYPES = [
        'template' => 'Template',
        'instructions' => 'Instructions',
        'parts_manual' => 'Parts manual',
        'spec_sheet' => 'Spec sheet',
        'other' => 'Other',
    ];

    protected $fillable = ['item_id', 'doc_type', 'title', 'file_name', 'file_path', 'file_size', 'file_mime', 'uploaded_by'];

    protected $casts = ['file_size' => 'integer'];

    protected $appends = ['url'];

    public function item()
    {
        return $this->belongsTo(ConfiguratorHwlibItem::class, 'item_id');
    }

    public function getUrlAttribute(): string
    {
        return '/storage/'.$this->file_path;
    }
}
