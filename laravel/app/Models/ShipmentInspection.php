<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentInspection extends Model
{
    protected $fillable = [
        'shipment_id', 'phase', 'result', 'performed_by_user_id', 'submitted_by_external',
        'performed_at', 'notes', 'source', 'submission_uuid',
    ];

    protected $casts = ['performed_at' => 'datetime'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function items()
    {
        return $this->hasMany(ShipmentInspectionItem::class, 'inspection_id');
    }

    public function photos()
    {
        return $this->hasMany(ShipmentInspectionPhoto::class, 'inspection_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    /** Any failed critical item fails the inspection; other failures pass with issues. */
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
