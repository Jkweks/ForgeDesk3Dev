<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldAssetInspection extends Model
{
    protected $fillable = [
        'field_asset_id', 'template_name', 'performed_by_user_id', 'submitted_by_external', 'performed_at',
        'meter', 'result', 'notes', 'source', 'submission_uuid',
    ];

    protected $casts = ['performed_at' => 'datetime', 'meter' => 'decimal:1'];

    public function asset()
    {
        return $this->belongsTo(FieldAsset::class, 'field_asset_id');
    }

    public function items()
    {
        return $this->hasMany(FieldAssetInspectionItem::class, 'inspection_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function documents()
    {
        return $this->hasMany(FieldAssetDocument::class, 'inspection_id');
    }

    /** fail if any item failed and it is critical; pass_with_issues for any other failure. */
    public static function resultFor(iterable $items): string
    {
        $result = 'pass';
        foreach ($items as $item) {
            if (($item['result'] ?? 'pass') === 'fail') {
                if (! empty($item['critical'])) {
                    return 'fail';
                }
                $result = 'pass_with_issues';
            }
        }

        return $result;
    }
}
