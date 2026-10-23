<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricationDocumentRevision extends Model
{
    protected $fillable = [
        'fabrication_document_id',
        'revision',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'archived_by',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function document()
    {
        return $this->belongsTo(FabricationDocument::class, 'fabrication_document_id');
    }
}
