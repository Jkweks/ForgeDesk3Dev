<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorFrameSeries;
use App\Models\ConfiguratorHwlibItemBacker;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\ConfiguratorPdfTemplate;
use App\Models\ConfiguratorSetting;
use App\Models\DoorFrameConfiguration;
use Illuminate\Support\Collection;

/**
 * Data for the fabricators' paper package ("source of truth" for assembling doors/frames and
 * installing hardware) — a port of fab_utils' hwlib-era report (workspace.html downloadHwlibPDF):
 *
 *  - per opening, a DOOR sheet and a FRAME sheet built from block rows (summary pills, extrusion,
 *    component / weatherstrip / hardware, hwlib hardware schedule, backers & fasteners, resolved
 *    hardware variables, inspection sign-off) whose order/width/visibility comes from the saved
 *    layout template;
 *  - whole-set pages: cut list, stock-length requirements (sticks), hwlib bill of materials and
 *    the field-install pick list.
 *
 * Unlike fab_utils (which recomputed from saved outputs) this reads the configuration's *actual
 * parts* — including manual edits — so the paper matches what is reserved and cut.
 *
 * Output is plain JSON; the /config/package page lays it out (CSS grid, browser print).
 */
class PackageReportService
{
    public const FINISH_LABELS = ['BL' => 'Black Anodized', 'C2' => 'Clear Anodized', 'DB' => 'Dark Bronze Anodized'];

    /** Variables printed highlighted on every sheet as "verify by hand", whether or not overridden. */
    public const ALWAYS_HIGHLIGHT = ['PANIC_BACKSET'];

    /** Block catalogue per report type: key => [label, alwaysShow, columns?] — shared with the template editor. */
    public const BLOCKS = [
        'door' => [
            'summary' => ['Configuration Summary', false, null],
            'hinge_prep' => ['Hinge Prep Locations', false, ['Hinge', 'From Door Top', 'From Door Bottom', 'Spacing From Previous']],
            'extrusion' => ['Extrusion Output', true, ['Description', 'Part Number', 'Qty', 'Length']],
            'component' => ['Component Output', true, ['Description', 'Part Number', 'Qty', 'Notes']],
            'hwlib_hardware' => ['Hwlib Hardware Schedule', false, ['Category', 'Item', 'Model / PN', 'Series', 'Qty', 'Notes', 'Variable Overrides']],
            'hwlib_backers' => ['Hwlib Backers & Fasteners', false, ['Description', 'Part Number', 'Qty', 'For']],
            'hwlib_variables' => ['Hwlib Hardware Variables', false, null],
            'hwlib_inspection' => ['Inspection Sign-Off', false, null],
        ],
        'frame' => [
            'summary' => ['Configuration Summary', false, null],
            'hinge_prep' => ['Hinge Prep Locations', false, ['Hinge', 'From Head', 'From Floor', 'Spacing From Previous']],
            'extrusion' => ['Extrusion Output', true, ['Description', 'Part Number', 'Qty', 'Length']],
            'weatherstrip' => ['Weatherstripping & Gaskets', false, ['Description', 'Part Number', 'Qty', 'Notes']],
            'hardware' => ['Hardware & Components', false, ['Description', 'Part Number', 'Qty', 'Notes']],
            'hwlib_hardware' => ['Hwlib Hardware Schedule', false, ['Category', 'Item', 'Model / PN', 'Series', 'Qty', 'Notes', 'Variable Overrides']],
            'hwlib_backers' => ['Hwlib Backers & Fasteners', false, ['Description', 'Part Number', 'Qty', 'For']],
            'hwlib_variables' => ['Hwlib Hardware Variables', false, null],
            'hwlib_inspection' => ['Inspection Sign-Off', false, null],
        ],
    ];

    public const DEFAULT_LAYOUTS = [
        'door' => [
            ['key' => 'summary', 'span' => 12, 'enabled' => true], ['key' => 'hinge_prep', 'span' => 12, 'enabled' => true],
            ['key' => 'extrusion', 'span' => 12, 'enabled' => true], ['key' => 'component', 'span' => 12, 'enabled' => true], ['key' => 'hwlib_hardware', 'span' => 12, 'enabled' => true],
            ['key' => 'hwlib_backers', 'span' => 12, 'enabled' => true], ['key' => 'hwlib_variables', 'span' => 12, 'enabled' => true],
            ['key' => 'hwlib_inspection', 'span' => 12, 'enabled' => true],
        ],
        'frame' => [
            ['key' => 'summary', 'span' => 12, 'enabled' => true], ['key' => 'hinge_prep', 'span' => 12, 'enabled' => true],
            ['key' => 'extrusion', 'span' => 12, 'enabled' => true], ['key' => 'weatherstrip', 'span' => 6, 'enabled' => true], ['key' => 'hardware', 'span' => 6, 'enabled' => true],
            ['key' => 'hwlib_hardware', 'span' => 12, 'enabled' => true], ['key' => 'hwlib_backers', 'span' => 12, 'enabled' => true],
            ['key' => 'hwlib_variables', 'span' => 12, 'enabled' => true], ['key' => 'hwlib_inspection', 'span' => 12, 'enabled' => true],
        ],
    ];

    /** @var array<string, ConfiguratorHwlibVariable> */
    private array $variablesByCode = [];

    /**
     * @param  Collection<int, DoorFrameConfiguration>  $configs
     * @param  array{doors?: bool, frames?: bool, cut_list?: bool, stock?: bool, bom?: bool, field_install?: bool}  $sections
     */
    public function build(Collection $configs, array $sections = []): array
    {
        $sections = array_merge(['doors' => true, 'frames' => true, 'cut_list' => true, 'stock' => true, 'bom' => true, 'field_install' => true], $sections);

        $configs->loadMissing([
            'businessJob', 'workOrder', 'doors', 'openingSpecs',
            'frameConfig.parts.product', 'doorConfigs.parts.product',
            'hardwareLinks.item.category', 'hardwareLinks.values',
        ]);
        $this->variablesByCode = ConfiguratorHwlibVariable::all()->keyBy('code')->all();

        $sorted = $configs->sortBy(fn ($c) => $this->tagLabel($c), SORT_NATURAL | SORT_FLAG_CASE)->values();

        // One sheet per physical piece: a door page per LEAF of every door tag (a pair is two), and a
        // frame page per tag. Cut list / stock / BOM below stay whole-job totals.
        $records = [];
        $allExtrusions = [];
        $openings = 0;
        foreach ($sorted as $config) {
            $variables = $this->resolveVariables($config);
            $tags = $this->tags($config);
            $framePerPair = $config->openingSpecs?->opening_type === 'pair' && count($tags) > 1;
            $openings += count($tags);

            foreach ($this->configExtrusions($config) as $e) {
                $allExtrusions[] = $e;
            }

            foreach ($tags as $tag) {
                if ($config->includesDoor() && $sections['doors']) {
                    foreach ($this->leavesForTag($config, $tag) as $leaf) {
                        $records[] = $this->sheet($config, 'door', $tag, empty($leaf['merged']) ? count($tags) : 1, $leaf, $variables);
                    }
                }
                if ($config->includesFrame() && $sections['frames'] && ! $framePerPair) {
                    $records[] = $this->sheet($config, 'frame', $tag, count($tags), null, $variables);
                }
            }

            // A pair is two doors in one frame: even when each leaf carries its own tag, print one frame
            // sheet for the opening, tagged with both.
            if ($framePerPair && $config->includesFrame() && $sections['frames']) {
                $records[] = $this->sheet($config, 'frame', implode(' / ', $tags), 1, null, $variables);
            }
        }

        $cutList = $this->cutList($allExtrusions);

        $first = $sorted->first();

        return [
            'meta' => [
                'job' => $this->jobName($sorted),
                'work_order' => $sorted->pluck('workOrder.release_token')->filter()->unique()->implode(', '),
                'date' => now()->format('F j, Y'),
                'records' => $openings,
            ],
            'records' => $records,
            'cut_list' => $sections['cut_list'] ? array_map(fn ($r) => [$r['pn'], $this->fmtLen($r['len']), (string) $this->fmtQty($r['qty']), $r['src']], $cutList) : [],
            'stock' => $sections['stock'] ? $this->stockList($cutList) : [],
            'bom' => $sections['bom'] ? $this->bom($sorted) : [],
            'field_install' => $sections['field_install'] ? $this->fieldInstall($sorted) : [],
            'blocks' => self::BLOCKS,
            'layouts' => [
                'door' => ConfiguratorPdfTemplate::layoutFor('door'),
                'frame' => ConfiguratorPdfTemplate::layoutFor('frame'),
            ],
        ];
    }

    /**
     * Saw-ready cut list, one row per extrusion cut per door tag. Columns: SKU, finish code, length, quantity,
     * job, work order, door tag, 0, 0, part use, 0, 0. Quantities divide the same way the sheets do
     * (a pair's frame prints once, tagged with both leaves).
     *
     * @param  Collection<int, DoorFrameConfiguration>  $configs
     * @return array<int, array<int, string|int|float>>
     */
    public function cutListCsvRows(Collection $configs, array $sections = []): array
    {
        $sections = array_merge(['doors' => true, 'frames' => true], $sections);
        $configs->loadMissing(['businessJob', 'workOrder', 'doors', 'openingSpecs', 'frameConfig.parts.product', 'doorConfigs.parts.product']);
        $sorted = $configs->sortBy(fn ($c) => $this->tagLabel($c), SORT_NATURAL | SORT_FLAG_CASE)->values();

        $rows = [];
        $emit = function (DoorFrameConfiguration $config, $parts, string $tag, int $divisor) use (&$rows) {
            foreach ($parts as $part) {
                if (! in_array($part->source_type, ['extrusion', 'profile'], true) || $part->calculated_length === null || ! $part->product) {
                    continue;
                }
                $qty = (float) $part->quantity / max(1, $divisor);
                if ($qty <= 0.00001) {
                    continue;
                }
                $rows[] = [
                    $part->product->part_number, $part->product->finish ?? '', number_format((float) $part->calculated_length, 3, '.', ''),
                    $this->fmtQty($qty), $config->businessJob?->job_number ?? '', $config->workOrder?->release_token ?? '',
                    $tag, 0, 0, $part->part_label, 0, 0,
                ];
            }
        };

        foreach ($sorted as $config) {
            $tags = $this->tags($config);
            $framePerPair = $config->openingSpecs?->opening_type === 'pair' && count($tags) > 1;

            if ($config->includesDoor() && $sections['doors']) {
                foreach ($tags as $tag) {
                    // A merged pair stores each tag's leaf parts on its own door config: no dividing.
                    $merged = $this->leavesForTag($config, $tag)[0]['door_config'] ?? null;
                    if ($merged) {
                        $emit($config, $merged->parts, $tag, 1);
                    } else {
                        $emit($config, $config->doorConfigs->first()?->parts ?? collect(), $tag, count($tags));
                    }
                }
            }
            if ($config->includesFrame() && $sections['frames']) {
                $frameParts = $config->frameConfig?->parts ?? collect();
                if ($framePerPair) {
                    $emit($config, $frameParts, implode(' / ', $tags), 1);
                } else {
                    foreach ($tags as $tag) {
                        $emit($config, $frameParts, $tag, count($tags));
                    }
                }
            }
        }

        return $rows;
    }

    // ── Per-piece sheets ────────────────────────────────────────────────────────────────

    /** Parts that exist once per pair (not once per leaf): they print on the active / inactive leaf only. */
    private const ACTIVE_LEAF_ONLY = ['ACTIVE ASTRAGAL STILE', 'ASTRAGAL'];

    private const INACTIVE_LEAF_ONLY = ['INACTIVE MEETING STILE'];

    /** @return array<int, string> physical door tags of this configuration (one set of parts each) */
    private function tags(DoorFrameConfiguration $config): array
    {
        $tags = $config->doors->pluck('door_tag')->filter()->values()->all();

        return $tags ?: ["Config #{$config->id}"];
    }

    /**
     * Leaves for one door tag's sheets. A merged pair is one leaf per tag, built from that tag's own door
     * config (parts are already per leaf); anything else follows leaves().
     *
     * @return array<int, array{role: string, label: ?string, active: ?bool}>
     */
    private function leavesForTag(DoorFrameConfiguration $config, string $tag): array
    {
        $door = $config->isMergedPair() ? $config->doors->firstWhere('door_tag', $tag) : null;
        $doorConfig = $door?->leaf ? $config->doorConfigs->firstWhere('leaf', $door->leaf) : null;
        if (! $doorConfig) {
            return $this->leaves($config);
        }

        $active = $door->leaf === 'active';
        $activeIsRight = $config->openingSpecs?->deriveDoorHanding() !== 'PAIR-LHRA';
        $right = $active === $activeIsRight;

        return [['role' => $right ? 'rh' : 'lh', 'label' => $right ? 'RH' : 'LH', 'active' => $active, 'door_config' => $doorConfig, 'merged' => true]];
    }

    /**
     * The leaves a door sheet is printed for. A single door is one leaf; a pair is two — LH and RH —
     * and which of them is active follows the pair's handing (RHRA: the RH leaf is active; LHRA: LH).
     *
     * @return array<int, array{role: string, label: ?string, active: ?bool}>
     */
    private function leaves(DoorFrameConfiguration $config): array
    {
        if ($config->openingSpecs?->opening_type !== 'pair') {
            return [['role' => 'single', 'label' => null, 'active' => null]];
        }

        $activeIsRight = $config->openingSpecs->deriveDoorHanding() !== 'PAIR-LHRA';

        return [
            ['role' => 'lh', 'label' => 'LH', 'active' => ! $activeIsRight],
            ['role' => 'rh', 'label' => 'RH', 'active' => $activeIsRight],
        ];
    }

    /**
     * One printed sheet: a single door leaf, or a frame. Everything on it is for that piece only —
     * parts divided out to one door tag and (for a pair) one leaf, and only the hardware, backers and
     * prep values that belong on that piece.
     */
    private function sheet(DoorFrameConfiguration $config, string $kind, string $tag, int $tagCount, ?array $leaf, array $variables): array
    {
        $spec = $config->openingSpecs;
        $finishCode = strtoupper((string) $spec?->finish);
        $finish = $finishCode ? (self::FINISH_LABELS[$finishCode] ?? $finishCode) : null;
        $wh = $spec ? $this->fmtNum($spec->door_opening_width).'" × '.$this->fmtNum($spec->door_opening_height).'"' : '—';
        $handing = $spec?->deriveDoorHanding() ?? '—';
        $isPairLeaf = $leaf && $leaf['role'] !== 'single';
        $leafText = $isPairLeaf ? $leaf['label'].' LEAF'.($leaf['active'] ? ' (ACTIVE)' : ' (INACTIVE)') : null;

        if ($kind === 'door') {
            $doorConfig = $leaf['door_config'] ?? $config->doorConfigs->first();
            $parts = $doorConfig?->parts ?? collect();
            $pills = [
                ['TAG', $tag], ...($isPairLeaf ? [['LEAF', $leafText]] : []),
                ['SERIES', $doorConfig?->door_series ?: '—'], ['STILE', $doorConfig?->stile_width ?: '—'],
                ['W × H', $wh], ['HANDING', $handing], ['GLASS', $spec?->glazing ?: '—'],
                ['BOT GAP', $this->fmtNum(ConfiguratorSetting::current()->bottom_gap).'"'],
                ['OPENING DEGREE', $doorConfig?->opening_angle !== null ? $doorConfig->opening_angle.'°' : '—'],
                ...($finish ? [['FINISH', $finish]] : []),
            ];
            $title = 'DOOR — '.$tag.($leafText ? ' · '.$leafText : '');
        } else {
            $frameConfig = $config->frameConfig;
            $parts = $frameConfig?->parts ?? collect();
            $series = $frameConfig?->frame_series_id ? ConfiguratorFrameSeries::with('frameSystem')->find($frameConfig->frame_series_id) : null;
            $pills = [
                ['TAG', $tag], ['SYSTEM', $series?->frameSystem?->name ?: '—'], ['SERIES', $series?->name ?: '—'],
                ['W × H', $wh], ['HANDING', $handing],
                ...($finish ? [['FINISH', $finish]] : []),
            ];
            $title = 'FRAME — '.$tag;
        }

        // Parts for this one piece (qty 0 = not on this leaf).
        $mine = [];
        foreach ($parts as $part) {
            $amount = $this->pieceAmount($part, $tagCount, $leaf);
            if ($amount > 0.00001) {
                $mine[] = [$part, $amount];
            }
        }

        $isExtrusion = fn ($part) => in_array($part->source_type, ['extrusion', 'profile'], true);
        $data = ['extrusion' => []];
        foreach ($mine as [$part, $amount]) {
            if ($isExtrusion($part)) {
                $data['extrusion'][] = [$part->part_label, $this->pn($part), (string) $this->fmtQty($amount), $part->calculated_length !== null ? $this->fmtLen($part->calculated_length) : '—'];
            }
        }

        $others = array_values(array_filter($mine, fn ($m) => ! $isExtrusion($m[0])));
        if ($kind === 'door') {
            $data['component'] = array_map(function ($m) {
                [$part, $amount] = $m;
                $isLength = $part->unit_type === 'length' && $part->calculated_length;

                return [$part->part_label, $this->pn($part), (string) $this->fmtQty($amount), $isLength ? $this->fmtNum($amount / 12, 1).' ft' : ''];
            }, $others);
        } else {
            $rows = array_map(fn ($m) => [
                'label' => $m[0]->part_label, 'pn' => $this->pn($m[0]), 'weather' => $this->isWeather($m[0]),
                'qty' => $m[0]->unit_type === 'length' && $m[0]->calculated_length ? ceil($m[1] / 12) : $m[1],
                'note' => $m[0]->unit_type === 'length' ? 'LF' : '',
            ], $others);
            $data['weatherstrip'] = $this->groupByPn(array_values(array_filter($rows, fn ($r) => $r['weather'])));
            $data['hardware'] = $this->groupByPn(array_values(array_filter($rows, fn ($r) => ! $r['weather'])));
        }

        $data['hinge_prep'] = $this->hingePrepRows($config, $kind);

        $links = $this->pageLinks($config, $kind, $leaf, $variables);
        $data['hwlib_hardware'] = $this->hardwareSchedule($config, $kind, $links, $variables, $leaf);
        $data['hwlib_backers'] = $this->backerRows($config, $kind, $links);
        $data['hwlib_variables'] = $this->variableRows($links, $variables, $kind);
        $data['hwlib_inspection'] = $this->inspectionRows($links, $variables, $kind);

        return ['kind' => $kind, 'title' => $title, 'pills' => $pills, 'data' => $data];
    }

    /**
     * Butt-hinge prep locations for one sheet (empty unless the opening uses butt hinges with a count and a
     * spacing standard). The standard measures from the door top; the door sheet prints that and the matching
     * distance from the door bottom, the frame sheet prints it from the head (the door hangs the top gap below
     * it) and from the finished floor (door bottom sits the bottom gap above it). Rows are read off a tape, so
     * fractions with the decimal alongside, plus the centre-to-centre spacing from the hinge above.
     */
    private function hingePrepRows(DoorFrameConfiguration $config, string $kind): array
    {
        $spec = $config->openingSpecs;
        $locations = $spec?->hingeLocations() ?? [];
        if (! $locations) {
            return [];
        }

        $settings = ConfiguratorSetting::current();
        $doorHeight = (float) $spec->door_opening_height;
        $fmt = fn (float $v) => $this->fractionInch((string) round($v, 4)).' ('.$this->fmtNum($v, 4).')';

        $rows = [];
        $previous = null;
        foreach ($locations as $loc) {
            $fromTop = (float) $loc['distance_from_top'];
            $fromBottom = $doorHeight - $fromTop;
            $first = $kind === 'door'
                ? $fromTop
                : $fromTop + (float) $settings->top_gap;
            $second = $kind === 'door'
                ? $fromBottom
                : $fromBottom + (float) $settings->bottom_gap;

            $rows[] = [
                str_replace(', from door top', '', $loc['label']), $fmt($first), $fmt($second),
                $previous === null ? '—' : $fmt($fromTop - $previous),
            ];
            $previous = $fromTop;
        }

        return $rows;
    }

    /**
     * How much of a part belongs on ONE sheet. Parts are stored for the whole configuration (all door
     * tags, both leaves of a pair): divide out the tag count, then — for a pair — split between the
     * leaves, except pieces that exist once per pair, which belong to the active (or inactive) leaf only.
     * Roll stock (gasket) is a total in inches; it divides the same way.
     */
    private function pieceAmount($part, int $tagCount, ?array $leaf): float
    {
        $isRoll = $part->unit_type === 'length' && (float) $part->calculated_length > 0 && ! in_array($part->source_type, ['extrusion', 'profile'], true);
        $total = $isRoll ? (float) $part->calculated_length * max(1.0, (float) $part->quantity) : (float) $part->quantity;
        $perTag = $total / max(1, $tagCount);

        // Merged pair: this leaf's door config already holds only this leaf's parts.
        if (! $leaf || $leaf['role'] === 'single' || ! empty($leaf['merged'])) {
            return $perTag;
        }

        $label = strtoupper((string) $part->part_label);
        if (in_array($label, self::ACTIVE_LEAF_ONLY, true)) {
            return $leaf['active'] ? $perTag : 0.0;
        }
        if (in_array($label, self::INACTIVE_LEAF_ONLY, true)) {
            return $leaf['active'] ? 0.0 : $perTag;
        }

        return $perTag / 2;
    }

    /** Every extrusion of a configuration (all tags, both kinds) for the whole-job cut list / stock pages. */
    private function configExtrusions(DoorFrameConfiguration $config): array
    {
        $out = [];
        foreach ([['Door', $config->includesDoor() ? $config->doorConfigs->flatMap(fn ($dc) => $dc->parts) : collect()], ['Frame', $config->includesFrame() ? ($config->frameConfig?->parts ?? collect()) : collect()]] as [$src, $parts]) {
            foreach ($parts as $p) {
                if (in_array($p->source_type, ['extrusion', 'profile'], true) && $p->calculated_length !== null && $p->product) {
                    $out[] = ['pn' => $this->pn($p), 'len' => (float) $p->calculated_length, 'qty' => (float) $p->quantity, 'src' => $src, 'stock' => $p->product->configurator_length ? (float) $p->product->configurator_length : null];
                }
            }
        }

        return $out;
    }

    /**
     * The hardware links that belong on this sheet. On a pair leaf, an "active" / "inactive" link only
     * goes on that leaf. Then only items with something to do on this piece: a prep value or backer for
     * this side (door / frame) — an item with nothing side-specific at all (a pull, say) belongs on the door.
     *
     * @return Collection<int, \App\Models\ConfiguratorHwlibLink>
     */
    private function pageLinks(DoorFrameConfiguration $config, string $kind, ?array $leaf, array $variables): Collection
    {
        return $config->hardwareLinks->filter(function ($link) use ($kind, $leaf, $variables) {
            if ($leaf && $leaf['role'] !== 'single') {
                if ($link->leaf === 'active' && ! $leaf['active']) {
                    return false;
                }
                if ($link->leaf === 'inactive' && $leaf['active']) {
                    return false;
                }
            }

            $rows = array_filter($variables[$link->id]['rows'] ?? [], fn ($v) => ! $v['hidden']);
            $forThisSide = array_filter($rows, fn ($v) => ! $v['side'] || $v['side'] === $kind);
            if ($forThisSide) {
                return true;
            }

            $backers = ConfiguratorHwlibItemBacker::where('item_id', $link->item_id)->where('series', $link->series)->whereNotNull('backer_id')->pluck('side');
            if ($backers->contains($kind)) {
                return true;
            }

            // Nothing side-specific at all: goes where it physically mounts — strikes, frame parts and
            // thresholds on the frame, everything else (pulls, closers...) on the door.
            if ($rows || $backers->isNotEmpty()) {
                return false;
            }

            return $kind === (in_array($link->item->category?->name, ['Strike', 'Frame', 'Threshold'], true) ? 'frame' : 'door');
        })->values();
    }

    /** Quantity of a link on one sheet: per leaf on a door; on a frame both leaves of a pair are covered. */
    private function sheetLinkQty($link, string $kind, DoorFrameConfiguration $config): int
    {
        return $kind === 'frame'
            ? $this->effectiveQty($link, $config->openingSpecs?->opening_type === 'pair')
            : (int) $link->quantity;
    }

    private function hardwareSchedule(DoorFrameConfiguration $config, string $kind, Collection $links, array $variables, ?array $leaf = null): array
    {
        $handing = $config->openingSpecs?->deriveDoorHanding() ?? '';
        $rows = [];
        foreach ($links as $link) {
            $item = $link->item;
            $overrides = collect($variables[$link->id]['rows'] ?? [])->filter(fn ($v) => $v['overridden'] && ! $v['hidden'])
                ->map(fn ($v) => "{$v['label']}: {$v['value']}".($v['unit'] ? '"' : ''))->implode('; ');
            $rows[] = [
                $item->category?->name ?? '', $item->name, $this->handedPn($item, $handing, $leaf), $link->series,
                (string) $this->sheetLinkQty($link, $kind, $config), (string) ($link->notes ?? ''), $overrides,
            ];
        }

        return $rows;
    }

    /** Backers/fasteners keyed to this sheet's side, for the links on this sheet. */
    private function backerRows(DoorFrameConfiguration $config, string $kind, Collection $links): array
    {
        $backers = [];
        $fasteners = [];
        foreach ($links as $link) {
            $itemBackers = ConfiguratorHwlibItemBacker::with('backer.fasteners.fastener')
                ->where('item_id', $link->item_id)->where('series', $link->series)->where('side', $kind)->whereNotNull('backer_id')->get();
            foreach ($itemBackers as $b) {
                $qty = (float) $b->qty * $this->sheetLinkQty($link, $kind, $config);
                if (! $qty) {
                    continue;
                }
                $pn = $b->pn ?: $b->backer?->pn;
                $backers[$b->backer_id] ??= ['pn' => $pn ?? '', 'desc' => $b->description ?: $b->backer?->description ?: '', 'ref' => $link->item->name, 'qty' => 0];
                $backers[$b->backer_id]['qty'] += $qty;
                foreach ($b->backer?->fasteners ?? [] as $bf) {
                    $fq = (float) $bf->qty * $qty;
                    if (! $fq) {
                        continue;
                    }
                    $fasteners[$bf->fastener_id] ??= ['pn' => $bf->fastener->pn ?? '', 'desc' => $bf->fastener->description ?? '', 'ref' => $pn ?: 'backer', 'qty' => 0];
                    $fasteners[$bf->fastener_id]['qty'] += $fq;
                }
            }
        }
        $sort = fn ($m) => collect($m)->sortBy('pn', SORT_NATURAL)->values()->all();

        return array_map(fn ($r) => [$r['desc'] ?: $r['pn'], $r['pn'], (string) $this->fmtQty($r['qty']), 'for '.$r['ref']], [...$sort($backers), ...$sort($fasteners)]);
    }

    /**
     * Every hardware link's variables, fully resolved (calculated included), in the category's
     * variable order. Not side-filtered here — a link on the door contributes its door-side rows
     * to the door sheet and its frame-side rows to the frame sheet.
     *
     * @return array<int, array{rows: array<int, array>}> keyed by link id
     */
    private function resolveVariables(DoorFrameConfiguration $config): array
    {
        if ($config->hardwareLinks->isEmpty()) {
            return [];
        }
        $resolver = new HwlibResolver($config);
        $out = [];
        foreach ($resolver->resolveAll() as $linkId => $byCode) {
            $rows = [];
            foreach ($byCode as $code => $result) {
                if ($result['value'] === null || $result['value'] === '') {
                    continue;
                }
                $var = $this->variablesByCode[$code] ?? null;
                if (! $var) {
                    continue;
                }
                $rows[] = [
                    'code' => $code, 'label' => $var->label, 'unit' => $var->unit, 'side' => $var->side,
                    'value' => $result['value'], 'overridden' => (bool) $result['overridden'],
                    'hidden' => ! $var->show_in_report, 'inspection' => (bool) $var->is_inspection,
                    'configured' => $resolver->isConfigured($linkId, $code),
                ];
            }
            $out[$linkId] = ['rows' => $rows];
        }

        return $out;
    }

    private function variableRows(Collection $links, array $variables, string $kind): array
    {
        $rows = [];
        foreach ($links as $link) {
            $all = $variables[$link->id]['rows'] ?? [];
            $vars = array_values(array_filter($all, fn ($v) => ! $v['hidden'] && (! $v['side'] || $v['side'] === $kind) && $this->passesSelector($v, $all, $kind)));
            if (! $vars || ! array_filter($vars, fn ($v) => $v['configured'])) {
                continue; // nothing actually entered for this item — skip its whole block
            }
            $rows[] = [['content' => $link->item->name, 'colSpan' => 2, 'bold' => true, 'fill' => true]];
            foreach ($vars as $v) {
                $rows[] = [
                    "   {$v['label']} ({$v['code']})",
                    ['content' => $v['value'].($v['unit'] ? '"' : ''), 'highlight' => $v['overridden'] || in_array($v['code'], self::ALWAYS_HIGHLIGHT, true)],
                ];
            }
        }

        return $rows;
    }

    private function inspectionRows(Collection $links, array $variables, string $kind): array
    {
        $rows = [];
        foreach ($links as $link) {
            $all = $variables[$link->id]['rows'] ?? [];
            foreach ($all as $v) {
                if ($v['hidden'] || ! $v['inspection'] || ($v['side'] && $v['side'] !== $kind) || ! $this->passesSelector($v, $all, $kind)) {
                    continue;
                }
                $rows[] = [($link->item->category?->name ?? '').' — '.$this->displayLabel($v, $all)." ({$v['code']})", $v['unit'] ? $this->fractionInch($v['value']) : $v['value']];
            }
        }

        return $rows;
    }

    /**
     * Some variables apply to whichever side the installer measured from, chosen per opening by a
     * sibling "<CODE>_DF" select ("Door"/"Frame"). If set, only the matching sheet prints it.
     */
    private function passesSelector(array $v, array $all, string $kind): bool
    {
        $df = collect($all)->firstWhere('code', $v['code'].'_DF');
        if (! $df || ! $df['value']) {
            return true;
        }
        $want = strtolower(trim($df['value']));

        return ! in_array($want, ['door', 'frame'], true) || $want === $kind;
    }

    /** "<CODE>_DESC" names what that point physically measures on this model; used in place of the generic label. */
    private function displayLabel(array $v, array $all): string
    {
        $desc = collect($all)->firstWhere('code', $v['code'].'_DESC');

        return ($desc && $desc['value']) ? $desc['value'] : $v['label'];
    }

    // ── Whole-set pages ────────────────────────────────────────────────────────────────

    /** @return array<int, array{pn: string, len: float, qty: float, src: string, stock: ?float}> */
    private function cutList(array $extrusions): array
    {
        $map = [];
        foreach ($extrusions as $e) {
            $key = $e['pn'].'|'.number_format($e['len'], 6, '.', '');
            if (isset($map[$key])) {
                $map[$key]['qty'] += $e['qty'];
            } else {
                $map[$key] = $e;
            }
        }
        $list = array_values($map);
        usort($list, fn ($a, $b) => strcmp($a['pn'], $b['pn']) ?: $a['len'] <=> $b['len']);

        return $list;
    }

    /** Sticks to pull per part number (see StickYield). */
    private function stockList(array $cutList): array
    {
        $byPn = [];
        foreach ($cutList as $c) {
            $byPn[$c['pn']] ??= ['stock' => StickYield::stockLength($c['pn'], $c['stock']), 'lineal' => 0.0, 'cuts' => []];
            $byPn[$c['pn']]['lineal'] += $c['qty'] * $c['len'];
            for ($i = 0; $i < (int) round($c['qty']); $i++) {
                $byPn[$c['pn']]['cuts'][] = $c['len'];
            }
        }
        ksort($byPn);

        $rows = [];
        foreach ($byPn as $pn => $r) {
            $rows[] = [$pn, $this->fmtNum($r['stock']).'"', number_format($r['lineal'], 2, '.', '').'"', (string) StickYield::sticks($r['cuts'], $r['stock'])];
        }

        return $rows;
    }

    /** Whole-set hardware BOM: hardware items, then backers, then fasteners, each aggregated. */
    private function bom(Collection $configs): array
    {
        $hardware = [];
        $backers = [];
        $fasteners = [];
        foreach ($configs as $config) {
            $isPair = $config->openingSpecs?->opening_type === 'pair';
            $handing = $config->openingSpecs?->deriveDoorHanding() ?? '';
            $openings = max(1, (int) $config->quantity);
            $sides = array_values(array_filter([$config->includesDoor() ? 'door' : null, $config->includesFrame() ? 'frame' : null]));
            foreach ($config->hardwareLinks as $link) {
                $item = $link->item;
                $qty = $this->effectiveQty($link, $isPair) * $openings;
                foreach (HandedHardware::variants($link, $item, $isPair, $handing) as [$suffix, $variantQty]) {
                    $pn = $this->formatPn($item, $suffix);
                    $key = $item->id.'|'.$pn;
                    $hardware[$key] ??= ['Hardware', $item->category?->name ?? '', $item->name, $item->manufacturer ?? '', $pn, 0];
                    $hardware[$key][5] += $variantQty * $openings;
                }

                $itemBackers = ConfiguratorHwlibItemBacker::with('backer.fasteners.fastener')
                    ->where('item_id', $item->id)->where('series', $link->series)->whereIn('side', $sides)->whereNotNull('backer_id')->get();
                foreach ($itemBackers as $b) {
                    $bq = (float) $b->qty * $qty;
                    $bpn = $b->pn ?: $b->backer?->pn ?? '';
                    $backers[$b->backer_id] ??= ['Backer', 'Backers', $b->description ?: $b->backer?->description ?: '', 'for '.$item->name, $bpn, 0];
                    $backers[$b->backer_id][5] += $bq;
                    foreach ($b->backer?->fasteners ?? [] as $bf) {
                        $fasteners[$bf->fastener_id] ??= ['Fastener', 'Fasteners', $bf->fastener->description ?? '', 'for '.$bpn, $bf->fastener->pn ?? '', 0];
                        $fasteners[$bf->fastener_id][5] += (float) $bf->qty * $bq;
                    }
                }
            }
        }
        usort($hardware, fn ($a, $b) => strcasecmp($a[1], $b[1]) ?: strcasecmp($a[2], $b[2]));
        $byPn = fn ($m) => collect($m)->sortBy(fn ($r) => $r[4], SORT_NATURAL)->values()->all();

        return array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[3], $r[4], (string) $this->fmtQty($r[5])], [...$hardware, ...$byPn($backers), ...$byPn($fasteners)]);
    }

    /** One row per distinct field-install item: total qty, doors listed in natural order. */
    private function fieldInstall(Collection $configs): array
    {
        $map = [];
        foreach ($configs as $config) {
            $isPair = $config->openingSpecs?->opening_type === 'pair';
            $handing = $config->openingSpecs?->deriveDoorHanding() ?? '';
            $tags = $config->doors->pluck('door_tag')->filter()->values()->all() ?: [$this->tagLabel($config)];
            foreach ($config->hardwareLinks as $link) {
                if (! $link->item->field_install) {
                    continue;
                }
                foreach (HandedHardware::variants($link, $link->item, $isPair, $handing) as [$suffix, $variantQty]) {
                    $pn = $this->formatPn($link->item, $suffix);
                    $key = $link->item->id.'|'.$pn;
                    $map[$key] ??= ['name' => $link->item->name, 'pn' => $pn, 'qty' => 0, 'doors' => []];
                    $map[$key]['qty'] += $variantQty * max(1, (int) $config->quantity);
                    array_push($map[$key]['doors'], ...$tags);
                }
            }
        }
        $rows = [];
        foreach ($map as $r) {
            $doors = array_values(array_unique($r['doors']));
            natcasesort($doors);
            $rows[] = [$r['name'], $r['pn'], (string) $r['qty'], implode(' | ', $doors)];
        }
        usort($rows, fn ($a, $b) => strcasecmp($a[0], $b[0]));

        return $rows;
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────

    private function tagLabel(DoorFrameConfiguration $config): string
    {
        return $config->doors->pluck('door_tag')->filter()->implode(' / ') ?: "Config #{$config->id}";
    }

    private function jobName(Collection $configs): string
    {
        return $configs->map(fn ($c) => $c->businessJob?->job_name ?: $c->businessJob?->job_number)->filter()->unique()->implode(', ');
    }

    private function pn($part): string
    {
        $p = $part->product;

        return $p ? $p->part_number.($p->finish ? '-'.$p->finish : '') : '';
    }

    private function isWeather($part): bool
    {
        return $part->unit_type === 'length'
            || (bool) preg_match('/felt|gasket|seal|weather/i', (string) ($part->product?->description ?? $part->part_label));
    }

    /** Collapse rows sharing a part number (first label wins), summing quantity. */
    private function groupByPn(array $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            if (isset($map[$r['pn']])) {
                $map[$r['pn']]['qty'] += $r['qty'];
            } else {
                $map[$r['pn']] = $r;
            }
        }

        return array_values(array_map(fn ($r) => [$r['label'], $r['pn'], (string) $this->fmtQty($r['qty']), $r['note']], $map));
    }

    private function effectiveQty($link, bool $isPair): int
    {
        return ($link->leaf === 'both' && $isPair) ? (int) $link->quantity * 2 : (int) $link->quantity;
    }

    /**
     * What the fabricator reads in the Model / PN column. A handed item shows its stocked part number
     * with the hand applied (P1421L) followed by the model, so the part can be pulled by number:
     * "P1421L · 4510 Deadlatch". On a pair's leaf sheet the hand is that leaf's own.
     */
    private function handedPn($item, string $handing, ?array $leaf = null): string
    {
        if (! $item->handed) {
            return $this->formatPn($item, '');
        }

        $suffix = ($leaf && $leaf['role'] !== 'single')
            ? ($leaf['label'] === 'LH' ? 'L' : 'R')
            : HandedHardware::suffix($handing);

        return $this->formatPn($item, $suffix);
    }

    private function formatPn($item, string $suffix): string
    {
        if (! $item->handed) {
            return $item->model_number ?: $item->pn ?: '';
        }
        if ($item->pn) {
            return $item->pn.$suffix.($item->model_number ? ' · '.$item->model_number : '');
        }

        return ($item->model_number ?: '').$suffix;
    }

    /** Inspection values are read off a tape: nearest 1/16", reduced (1/2, 1/4, 1 3/8 ...). */
    private function fractionInch(string $value): string
    {
        if (! is_numeric($value)) {
            return $value.'"';
        }
        $num = (float) $value;
        $sign = $num < 0 ? '-' : '';
        $abs = abs($num);
        $whole = (int) floor($abs);
        $sixteenths = (int) round(($abs - $whole) * 16);
        if ($sixteenths === 16) {
            $sixteenths = 0;
            $whole++;
        }
        if ($sixteenths === 0) {
            return "{$sign}{$whole}\"";
        }
        $g = $this->gcd($sixteenths, 16);
        $frac = ($sixteenths / $g).'/'.(16 / $g);

        return $whole ? "{$sign}{$whole} {$frac}\"" : "{$sign}{$frac}\"";
    }

    private function gcd(int $a, int $b): int
    {
        return $b ? $this->gcd($b, $a % $b) : $a;
    }

    private function fmtNum($v, int $dp = 4): string
    {
        return rtrim(rtrim(number_format((float) $v, $dp, '.', ''), '0'), '.');
    }

    private function fmtQty($v): string|float|int
    {
        $v = (float) $v;

        return floor($v) == $v ? (int) $v : (float) rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    private function fmtLen($v): string
    {
        return $this->fmtNum($v, 4).'"';
    }
}
