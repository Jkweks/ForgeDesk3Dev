<?php

namespace App\Dashboard;

use App\Models\FdWorkOrder;
use App\Models\User;

/**
 * The Work Orders page's table columns (WO_COLUMNS in fabrication/work-orders.blade.php), so the
 * dashboard's Work Order Table can mirror the columns a user chose there.
 *
 * Preference storage is users.wo_column_prefs: {order: [keys], hidden: [keys]}, or a legacy bare
 * array of hidden keys from before ordering existed. The page keeps any key missing from `order`
 * at the end, and so does {@see self::resolve()}.
 *
 * Cells are plain strings, or {text, class} to render as a badge.
 */
class WorkOrderColumns
{
    /** key => [label, right-aligned?] in the page's default order. */
    public const COLUMNS = [
        'priority' => ['#', false],
        'job_name' => ['Job Name', false],
        'release' => ['Release', false],
        'pm' => ['PM', false],
        'due' => ['Due', false],
        'est_start' => ['Est. Start', false],
        'est_completion' => ['Est. Complete', false],
        'work_content' => ['Work Content', true],
        'est_remaining' => ['Est. Remaining', true],
        'work_combined' => ['Work Content (Combined)', true],
        'assigned' => ['Assigned', false],
        'material' => ['Material', false],
        'elevations' => ['Elevations', true],
    ];

    /** Columns that need the (heavier) labour-estimate relations loaded. */
    public const ESTIMATE_COLUMNS = ['work_content', 'est_remaining', 'work_combined'];

    /** key => label, for settings UIs. */
    public static function options(): array
    {
        return array_map(fn ($c) => $c[0], self::COLUMNS);
    }

    /**
     * The column keys to show, in order.
     *
     * @param  string  $mode  'mine' follows the user's saved page preference; 'custom' shows only
     *                        $custom (still in the user's saved order).
     * @param  array<int, string>  $custom
     */
    public static function resolve(User $user, string $mode = 'mine', array $custom = []): array
    {
        $prefs = $user->wo_column_prefs;
        $valid = array_keys(self::COLUMNS);
        $legacy = is_array($prefs) && array_is_list($prefs) && $prefs !== [];

        $order = (! $legacy && is_array($prefs['order'] ?? null))
            ? array_values(array_filter($prefs['order'], fn ($k) => in_array($k, $valid, true)))
            : [];
        $order = array_values(array_unique(array_merge($order, array_diff($valid, $order))));

        $hidden = $legacy ? $prefs : (is_array($prefs['hidden'] ?? null) ? $prefs['hidden'] : []);

        if ($mode === 'custom' && $custom !== []) {
            return array_values(array_filter($order, fn ($k) => in_array($k, $custom, true)));
        }

        return array_values(array_filter($order, fn ($k) => ! in_array($k, $hidden, true)));
    }

    /** @return array<int, array{key: string, label: string, align?: string}> */
    public static function definitions(array $keys): array
    {
        return array_map(fn ($k) => ['key' => $k, 'label' => self::COLUMNS[$k][0]] + (self::COLUMNS[$k][1] ? ['align' => 'end'] : []), $keys);
    }

    public static function needsEstimates(array $keys): bool
    {
        return array_intersect($keys, self::ESTIMATE_COLUMNS) !== [];
    }

    /**
     * @param  int  $position  1-based list position, shown for priority when a work order has none (as the page does)
     */
    public static function cell(string $key, FdWorkOrder $wo, int $position): string|array
    {
        $job = $wo->businessJob;

        return match ($key) {
            'priority' => (string) ($wo->priority ?? $position),
            'job_name' => (string) ($job?->job_name ?? '—'),
            'release' => self::release($wo),
            'pm' => $job?->project_manager ? ['text' => self::initials($job->project_manager), 'class' => 'bg-blue-lt'] : '—',
            'due' => self::due($wo),
            'est_start' => self::date($wo->planned_start_date),
            'est_completion' => self::date($wo->planned_completion_date),
            'work_content' => self::hours($wo->estimateMinutes()['effective'] ?? null),
            'est_remaining' => self::hours($wo->estimateRemainingMinutes()['effective'] ?? null, blankZero: true),
            'work_combined' => self::combined($wo),
            'assigned' => $wo->assignedUsers->map(fn ($u) => $u->initials ?: mb_strtoupper(mb_substr($u->name, 0, 2)))->implode(' ') ?: '—',
            'material' => self::material($wo->material_delivery),
            'elevations' => ($wo->elevations_count ?? 0) > 0 ? ($wo->elevations_complete ?? 0).'/'.$wo->elevations_count.' done' : '—',
            default => '',
        };
    }

    private static function release(FdWorkOrder $wo): string|array
    {
        $label = $wo->businessJob ? "{$wo->businessJob->job_number}-{$wo->release_token}" : (string) $wo->release_token;

        return $wo->status === 'on_hold' ? ['text' => $label.' · On hold', 'class' => 'bg-orange-lt'] : $label;
    }

    /** First elevation due date, else the work order's own; red once it has passed (as the page does). */
    private static function due(FdWorkOrder $wo): string|array
    {
        $first = $wo->elevation_due_first ?? $wo->due_date;
        if (! $first) {
            return '—';
        }
        $date = \Illuminate\Support\Carbon::parse($first);

        return $date->lt(today()) ? ['text' => $date->format('m/d/Y'), 'class' => 'bg-red-lt'] : $date->format('m/d/Y');
    }

    private static function date($value): string
    {
        return $value ? $value->format('m/d/Y') : '—';
    }

    private static function hours(?float $minutes, bool $blankZero = false): string
    {
        if ($minutes === null || ($blankZero && ! $minutes)) {
            return '—';
        }

        return number_format($minutes / 60, 1).' h';
    }

    private static function combined(FdWorkOrder $wo): string
    {
        $total = $wo->estimateMinutes()['effective'] ?? null;
        if ($total === null) {
            return '—';
        }
        $remaining = ($wo->estimateRemainingMinutes()['effective'] ?? 0) / 60;

        return number_format($remaining, 1).' / '.number_format($total / 60, 1).' h';
    }

    private static function material(?string $value): array
    {
        return match ($value) {
            null, '' => ['text' => 'Pending', 'class' => 'bg-secondary-lt'],
            'In Shop' => ['text' => 'In Shop', 'class' => 'bg-success-lt'],
            'SOF' => ['text' => 'SOF', 'class' => 'bg-warning-lt'],
            default => ['text' => $value, 'class' => 'bg-info-lt'],
        };
    }

    /** "Last, First" or "First Last" -> initials, matching the page's pmInitials(). */
    private static function initials(string $name): string
    {
        $name = trim($name);
        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
        }
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) <= 1) {
            return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr(end($parts), 0, 1));
    }
}
