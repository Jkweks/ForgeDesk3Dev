<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A file Project Management works with. Registered the same way whether uploaded or reported by the F: watcher. */
class PmFile extends Model
{
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_FDRIVE = 'fdrive';

    protected $fillable = [
        'source', 'doc_type', 'original_name', 'storage_path', 'fdrive_path', 'sha256', 'size', 'file_mtime',
        'business_job_id', 'forge_doc_id', 'revision', 'status', 'error', 'uploaded_by',
    ];

    protected $casts = ['file_mtime' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id')->withoutGlobalScopes();
    }
}
