<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class FabricationDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'hwtype',
        'manufacturer',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'tags',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'file_size' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the public URL for the attached file.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        return '/storage/' . $this->file_path;
    }

    /**
     * Returns the file info array used by the frontend, or null.
     */
    public function getFileAttribute(): ?array
    {
        if (!$this->file_name) {
            return null;
        }
        return [
            'name' => $this->file_name,
            'size' => $this->file_size,
            'mime' => $this->file_mime,
            'url'  => $this->file_url,
        ];
    }

    /**
     * Delete the stored file when the model is force-deleted.
     */
    protected static function booted(): void
    {
        static::forceDeleted(function (FabricationDocument $doc) {
            if ($doc->file_path) {
                Storage::disk('public')->delete($doc->file_path);
            }
        });
    }
}
