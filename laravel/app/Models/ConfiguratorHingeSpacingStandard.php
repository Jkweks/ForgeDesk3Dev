<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHingeSpacingStandard extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'top_distance',
        'top_label',
        'bottom_distance',
        'bottom_reference',
        'bottom_label',
        'notes',
    ];

    protected $casts = [
        'top_distance' => 'decimal:4',
        'bottom_distance' => 'decimal:4',
    ];

    public static $bottomReferenceOptions = [
        'door_bottom' => 'Bottom of Door',
        'floor' => 'Finished Floor',
    ];

    /**
     * Compute each hinge's centerline-ish prep location as a distance from
     * the top of the door. The top hinge sits at top_distance from door top;
     * the bottom hinge sits at bottom_distance from either the door bottom
     * or the finished floor (floor = door bottom + $bottomGap); any hinges
     * in between are spaced evenly between those two points.
     *
     * @return array<int, array{index: int, distance_from_top: float, label: string}>
     */
    public function locations(float $doorHeight, int $hingeCount, float $bottomGap): array
    {
        $hingeCount = max(2, $hingeCount);

        $topOffset = (float) $this->top_distance;
        $floorFromTop = $doorHeight + $bottomGap;
        $bottomOffset = $this->bottom_reference === 'floor'
            ? $floorFromTop - (float) $this->bottom_distance
            : $doorHeight - (float) $this->bottom_distance;

        $locations = [];
        for ($i = 0; $i < $hingeCount; $i++) {
            $position = $hingeCount === 1
                ? $topOffset
                : $topOffset + ($i / ($hingeCount - 1)) * ($bottomOffset - $topOffset);

            $label = match (true) {
                $i === 0 => "Hinge 1 ({$this->top_label}, from door top)",
                $i === $hingeCount - 1 => "Hinge {$hingeCount} ({$this->bottom_label}, from door top)",
                default => 'Hinge '.($i + 1),
            };

            $locations[] = [
                'index' => $i + 1,
                'distance_from_top' => round($position, 4),
                'label' => $label,
            ];
        }

        return $locations;
    }
}
