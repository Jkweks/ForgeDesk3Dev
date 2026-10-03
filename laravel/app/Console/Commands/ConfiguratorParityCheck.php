<?php

namespace App\Console\Commands;

use App\Models\ConfiguratorFrameSeries;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameDoorConfig;
use App\Models\DoorFrameFrameConfig;
use App\Models\DoorFrameOpeningSpec;
use App\Models\Product;
use App\Services\Configurator\DoorBomGenerator;
use App\Services\Configurator\FrameBomGenerator;
use Illuminate\Console\Command;

/**
 * Parity harness: replays fab_utils' saved door/frame configs (inputs) through
 * ForgeDesk's BOM generators and diffs the result against the outputs
 * fab_utils itself saved. Read-only — builds unsaved models in memory and only
 * reads the catalog; nothing is written to the database.
 *
 * Input: storage/app/fab_utils_parity/saved_configs.json — array of
 * {id, label, status, inputs, outputs} rows from fab_utils' saved_configs.
 */
class ConfiguratorParityCheck extends Command
{
    protected $signature = 'configurator:parity-check
        {--path=fab_utils_parity/saved_configs.json : File under storage/app}
        {--kind= : Only "door" or "frame"}
        {--hwvars= : Compare resolved hardware variable values (HwlibResolver) from this file under storage/app (see tests/Parity/fab_utils_hwvars.js)}
        {--hwlib= : Compare hardware-library BOMs instead, from this file under storage/app (see tests/Parity/fab_utils_hwlib.js)}
        {--id=* : Only these fab_utils saved_config ids}
        {--verbose-diffs : Print every diff line, not just the first few per config}';

    protected $description = 'Compare ForgeDesk door/frame BOM output against fab_utils saved outputs (read-only)';

    public function handle(DoorBomGenerator $doorGen, FrameBomGenerator $frameGen): int
    {
        if ($this->option('hwlib')) {
            return $this->handleHwlib();
        }
        if ($this->option('hwvars')) {
            return $this->handleHwvars();
        }

        $file = storage_path('app/'.$this->option('path'));
        if (! is_file($file)) {
            $this->error("Not found: {$file}");

            return self::FAILURE;
        }

        $rows = json_decode(file_get_contents($file), true) ?: [];
        $ids = array_map('intval', $this->option('id'));

        $stats = ['door' => ['ok' => 0, 'diff' => 0, 'error' => 0], 'frame' => ['ok' => 0, 'diff' => 0, 'error' => 0]];
        $causes = [];

        foreach ($rows as $row) {
            $inputs = is_string($row['inputs']) ? json_decode($row['inputs'], true) : $row['inputs'];
            $outputs = is_string($row['outputs']) ? json_decode($row['outputs'], true) : $row['outputs'];
            $kind = isset($inputs['stile']) ? 'door' : 'frame';

            if (empty($outputs) || (int) ($inputs['qty'] ?? 1) <= 0) {
                continue; // reference produced nothing (e.g. legacy inputs fab_utils itself rejects)
            }

            if (($this->option('kind') && $this->option('kind') !== $kind) || ($ids && ! in_array((int) $row['id'], $ids, true))) {
                continue;
            }

            try {
                $result = $kind === 'door'
                    ? $doorGen->generate($this->buildDoorConfig($inputs))
                    : $frameGen->generate($this->buildFrameConfig($inputs));
            } catch (\Throwable $e) {
                $stats[$kind]['error']++;
                $msg = $e->getMessage();
                $causes["ERROR: {$msg}"][] = $row['id'];
                $this->line("<fg=red>#{$row['id']} {$kind} ERROR</> {$msg}");

                continue;
            }

            $expected = $this->normalizeReference($outputs);
            $actual = $this->normalizeActual($result['rows'], $this->linearFootPns($outputs));
            $diffs = $this->diff($expected, $actual);

            if (! $diffs) {
                $stats[$kind]['ok']++;

                continue;
            }

            $stats[$kind]['diff']++;
            $this->line("<fg=yellow>#{$row['id']} {$kind} DIFF</> ".($row['label'] ?? ''));
            foreach (($this->option('verbose-diffs') ? $diffs : array_slice($diffs, 0, 4)) as $d) {
                $this->line("    {$d}");
            }
            foreach ($result['warnings'] as $w) {
                $this->line("    warn: {$w}");
            }
        }

        $this->newLine();
        foreach ($stats as $kind => $s) {
            $this->info(sprintf('%-5s ok=%d diff=%d error=%d', $kind, $s['ok'], $s['diff'], $s['error']));
        }
        foreach ($causes as $cause => $list) {
            $this->line(sprintf('%s  (%d configs: %s)', $cause, count($list), implode(',', array_slice($list, 0, 8))));
        }

        return self::SUCCESS;
    }

    /**
     * Hardware-variable parity: fab_utils' own hwlibResolveVar results per link (see
     * tests/Parity/fab_utils_hwvars.js) vs HwlibResolver. Each fab_utils combo (a door + its paired
     * frame, sharing its hardware) is rebuilt as ONE configuration inside a transaction that is always
     * rolled back — the resolver reads the database, so the rows have to exist for a moment.
     */
    private function handleHwvars(): int
    {
        $data = json_decode(file_get_contents(storage_path('app/'.$this->option('hwvars'))), true) ?: [];
        $ok = $diff = 0;
        $byCode = [];

        foreach ($data as $combo) {
            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $job = \App\Models\BusinessJob::create(['job_number' => 'PARITY', 'job_name' => 'parity', 'status' => 'active']);
                $config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'quantity' => 1, 'status' => 'draft']);
                DoorFrameOpeningSpec::create(['configuration_id' => $config->id, 'opening_type' => 'single', 'hand_single' => 'rhr', 'door_opening_width' => 36, 'door_opening_height' => 84, 'hinging' => 'butt', 'finish' => 'c2']);
                $series = $combo['frame'] ? ConfiguratorFrameSeries::whereRaw('lower(name) = ?', [strtolower($combo['frame']['seriesName'])])
                    ->whereHas('frameSystem', fn ($q) => $q->whereRaw('lower(name) = ?', [strtolower($combo['frame']['sysName'])]))->first() : null;
                DoorFrameFrameConfig::create(['configuration_id' => $config->id, 'frame_series_id' => $series?->id]);
                DoorFrameDoorConfig::create(['configuration_id' => $config->id, 'door_series' => 'STANDARD', 'stile_width' => 'WIDE STILE', 'leaf_type' => 'single', 'handing' => 'RHR', 'hinge_type' => 'BUTT HINGES', 'opening_angle' => $combo['angle'], 'bottom_gap' => 0.6875, 'top_rail_label' => '5"', 'bot_rail_label' => '10"', 'mid_qty' => 0]);

                $linkMap = [];
                foreach ($combo['links'] as $l) {
                    $item = \App\Models\ConfiguratorHwlibItem::where('name', $l['item_name'])->first();
                    if (! $item) {
                        continue;
                    }
                    $link = \App\Models\ConfiguratorHwlibLink::create(['configuration_id' => $config->id, 'item_id' => $item->id, 'quantity' => $l['quantity'], 'series' => $l['series'], 'leaf' => $l['leaf']]);
                    foreach ($l['overrides'] as $code => $text) {
                        $var = \App\Models\ConfiguratorHwlibVariable::where('code', $code)->first();
                        if ($var) {
                            \App\Models\ConfiguratorHwlibLinkValue::create(['link_id' => $link->id, 'variable_id' => $var->id, 'value_text' => $text]);
                        }
                    }
                    $linkMap[$link->id] = $l;
                }

                $config->unsetRelations();
                $resolved = (new \App\Services\Configurator\HwlibResolver($config))->resolveAll();
                $comboDiffs = [];
                foreach ($linkMap as $linkId => $l) {
                    foreach ($l['results'] as $code => $exp) {
                        $act = $resolved[$linkId][$code] ?? ['value' => null, 'overridden' => false];
                        if (! $this->sameValue($exp['value'], $act['value']) || (bool) $exp['overridden'] !== (bool) $act['overridden']) {
                            $comboDiffs[] = sprintf('%s [%s] fab_utils=%s%s forgedesk=%s%s', $l['item_name'], $code, var_export($exp['value'], true), $exp['overridden'] ? '*' : '', var_export($act['value'], true), $act['overridden'] ? '*' : '');
                            $byCode[$code] = ($byCode[$code] ?? 0) + 1;
                        }
                    }
                }
                if ($comboDiffs) {
                    $diff++;
                    $this->line("<fg=yellow>combo {$combo['key']} DIFF</> (".count($comboDiffs).')');
                    foreach (array_slice($comboDiffs, 0, $this->option('verbose-diffs') ? 99 : 4) as $d) {
                        $this->line("    {$d}");
                    }
                } else {
                    $ok++;
                }
            } catch (\Throwable $e) {
                $this->line("<fg=red>combo {$combo['key']} ERROR</> ".$e->getMessage());
            } finally {
                \Illuminate\Support\Facades\DB::rollBack();
            }
        }

        arsort($byCode);
        $this->info("hwvars ok={$ok} diff={$diff}".($byCode ? '  by variable: '.json_encode($byCode) : ''));

        return self::SUCCESS;
    }

    private function sameValue($a, $b): bool
    {
        if ($a === null || $a === '' ) {
            return $b === null || $b === '';
        }
        if ($b === null || $b === '') {
            return false;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.00006;
        }

        return (string) $a === (string) $b;
    }

    /**
     * Hardware-library parity: fab_utils' own computeBOM output per saved config (see
     * tests/Parity/fab_utils_hwlib.js) vs HwlibBomGenerator on transient links resolved by
     * item name. Hardware items are only compared when the ForgeDesk item carries a stock PN
     * (items without one never produce a BOM row by design); backers/fasteners by base PN.
     */
    private function handleHwlib(): int
    {
        $data = json_decode(file_get_contents(storage_path('app/'.$this->option('hwlib'))), true) ?: [];
        $ids = array_map('intval', $this->option('id'));
        $gen = app(\App\Services\Configurator\HwlibBomGenerator::class);
        $ok = $diff = $skipped = 0;

        foreach ($data as $id => $entry) {
            if ($ids && ! in_array((int) $id, $ids, true)) {
                continue;
            }

            $links = collect();
            $unknown = [];
            foreach ($entry['links'] as $l) {
                $item = \App\Models\ConfiguratorHwlibItem::where('name', $l['item_name'])->first();
                if (! $item) {
                    $unknown[] = $l['item_name'];

                    continue;
                }
                $links->push(new \App\Models\ConfiguratorHwlibLink([
                    'item_id' => $item->id, 'quantity' => $l['quantity'], 'leaf' => $l['leaf'], 'series' => $l['series'],
                ]));
            }
            $links->each(fn ($link) => $link->setRelation('item', \App\Models\ConfiguratorHwlibItem::find($link->item_id)));

            $pair = (bool) preg_match('/pair/i', $entry['handing'] ?? '');
            $config = new DoorFrameConfiguration(['job_scope' => 'door_and_frame', 'quantity' => 1]);
            $config->setRelation('openingSpecs', new DoorFrameOpeningSpec(['opening_type' => $pair ? 'pair' : 'single', 'finish' => 'c2']));
            $config->setRelation('hardwareLinks', new \Illuminate\Database\Eloquent\Collection($links->all()));

            try {
                $rows = $links->isEmpty() ? [] : $gen->generate($config)['rows'];
            } catch (\Throwable $e) {
                $this->line("<fg=red>#{$id} ERROR</> ".$e->getMessage());

                continue;
            }

            $expected = [];
            foreach ($entry['rows'] as $r) {
                if ($r['type'] === 'Hardware') {
                    $expected['item:'.$r['name']] = ($expected['item:'.$r['name']] ?? 0) + $r['qty'];
                } else {
                    $k = strtolower($r['type']).':'.$this->basePn($r['pn']);
                    $expected[$k] = ($expected[$k] ?? 0) + $r['qty'];
                }
            }

            $skus = Product::whereIn('id', array_filter(array_column($rows, 'product_id')))->pluck('part_number', 'id');
            $actual = [];
            foreach ($rows as $r) {
                $k = $r['source_type'] === 'item'
                    ? 'item:'.$r['part_label']
                    : $r['source_type'].':'.$this->basePn($skus[$r['product_id']] ?? '?');
                $actual[$k] = ($actual[$k] ?? 0) + (float) $r['quantity'];
            }
            // Backers/fasteners resolve to a Product part_number that may differ in finish suffix only.
            ksort($expected);
            ksort($actual);

            $diffs = $this->diff($expected, $actual);
            if ($unknown) {
                $skipped++;
                $diffs[] = 'unmapped items: '.implode('; ', array_unique($unknown));
            }
            if (! $diffs) {
                $ok++;

                continue;
            }
            $diff++;
            $this->line("<fg=yellow>#{$id} hwlib DIFF</> ({$entry['handing']})");
            foreach (array_slice($diffs, 0, $this->option('verbose-diffs') ? 99 : 4) as $d) {
                $this->line("    {$d}");
            }
        }

        $this->info("hwlib ok={$ok} diff={$diff} (with unmapped items: {$skipped})");

        return self::SUCCESS;
    }

    private function buildDoorConfig(array $in): DoorFrameConfiguration
    {
        $handing = strtoupper($in['handing'] ?? '');
        $hinge = strtoupper($in['hingeType'] ?? '');
        $pair = str_starts_with($handing, 'PAIR') || str_starts_with($handing, 'CP PAIR');

        $spec = new DoorFrameOpeningSpec([
            'opening_type' => $pair ? 'pair' : 'single',
            'hand_single' => match ($handing) {
                'RH (INSWING)' => 'rh_inswing', 'LHR' => 'lhr', 'RHR' => 'rhr', default => 'lh_inswing',
            },
            'hand_pair' => $handing === 'PAIR-LHRA' ? 'lhra_active' : 'rhra_active',
            'door_opening_width' => $in['W'],
            'door_opening_height' => $in['H'],
            'hinging' => match ($hinge) {
                'BUTT HINGES' => 'butt', 'OFFSET PIVOTS' => 'pivot_offset', 'CENTER PIVOTS' => 'pivot_center', default => 'continuous',
            },
            'finish' => strtolower($in['finish'] ?? 'c2'),
            'glazing' => $in['glassThk'] ?? null,
        ]);

        $door = new DoorFrameDoorConfig([
            'door_series' => $in['series'] ?? null,
            'stile_width' => $in['stile'] ?? null,
            'top_rail_label' => $in['topRailLbl'] ?? null,
            'bot_rail_label' => $in['botRailLbl'] ?? null,
            'mid_rail_label' => ($in['midRailLbl'] ?? '') ?: null,
            'mid_qty' => (int) ($in['midQty'] ?? 0),
            'mid_loc1' => $in['midLoc1'] ?? 0,
            'mid_loc2' => $in['midLoc2'] ?? 0,
        ]);

        $config = new DoorFrameConfiguration(['quantity' => (int) ($in['qty'] ?? 1)]);
        $config->setRelation('openingSpecs', $spec);
        $config->setRelation('doorConfigs', collect([$door]));

        return $config;
    }

    private function buildFrameConfig(array $in): DoorFrameConfiguration
    {
        $series = ConfiguratorFrameSeries::whereRaw('lower(name) = ?', [strtolower($in['seriesName'] ?? '')])
            ->whereHas('frameSystem', fn ($q) => $q->whereRaw('lower(name) = ?', [strtolower($in['sysName'] ?? '')]))
            ->first();
        if (! $series) {
            throw new \RuntimeException("No frame series for {$in['sysName']} / {$in['seriesName']}");
        }

        $handing = strtoupper($in['handing'] ?? '');
        $pair = str_contains($handing, 'PAIR');

        $spec = new DoorFrameOpeningSpec([
            'opening_type' => $pair ? 'pair' : 'single',
            'door_opening_width' => $in['W'],
            'door_opening_height' => $in['H'],
            'finish' => strtolower($in['finish'] ?? 'c2'),
        ]);

        $frame = new DoorFrameFrameConfig([
            'frame_series_id' => $series->id,
            'has_transom' => (bool) ($in['hasTransom'] ?? false),
            'has_threshold' => (bool) ($in['hasThreshold'] ?? false),
            'transom_glazing' => ($in['glass'] ?? null) !== null && $in['glass'] !== '' ? $in['glass'] : null,
            'total_frame_height' => $in['TH'] ?? null,
        ]);

        $config = new DoorFrameConfiguration(['quantity' => (int) ($in['qty'] ?? 1)]);
        $config->setRelation('openingSpecs', $spec);
        $config->setRelation('frameConfig', $frame);

        return $config;
    }

    /** Strips the trailing 2-char finish suffix (E7318-C2 -> E7318); P2728-250 style PNs are left alone. */
    private function basePn(string $pn): string
    {
        $base = preg_replace('/-[A-Z0-9]{2}$/', '', $pn);

        // Frame gasket: fab_utils says generic P2728, ForgeDesk reserves the sized 250' roll (importer substitution).
        $from = array_search($base, ImportFabUtilsFrameCatalog::STOCK_SUBSTITUTIONS, true);

        return $from !== false ? $from : $base;
    }

    /** PNs fab_utils reports in linear feet (note "LF") rather than inches. */
    private function linearFootPns(array $out): array
    {
        $pns = [];
        foreach ($out['components'] ?? [] as $c) {
            if (($c['note'] ?? null) === 'LF') {
                $pns[] = $this->basePn($c['pn']);
            }
        }

        return array_unique($pns);
    }

    /** fab_utils {extrusions:[{pn,len,qty}], components:[{pn,qty,note?}]} -> comparable lines. */
    private function normalizeReference(array $out): array
    {
        $lines = [];
        foreach ($out['extrusions'] ?? [] as $e) {
            $lines[] = ['pn' => $this->basePn($e['pn']), 'len' => round((float) $e['len'], 3), 'qty' => (float) $e['qty']];
        }
        foreach ($out['components'] ?? [] as $c) {
            $lines[] = ['pn' => $this->basePn($c['pn']), 'len' => null, 'qty' => (float) $c['qty']];
        }

        return $this->aggregate($this->expandKitLines($lines));
    }

    /** ForgeDesk generator rows -> comparable lines (keyed on product SKU). */
    private function normalizeActual(array $rows, array $lfPns = []): array
    {
        $skus = Product::whereIn('id', array_filter(array_column($rows, 'product_id')))->pluck('sku', 'id');
        $lines = [];
        foreach ($rows as $r) {
            $isStockCut = $r['unit_type'] === 'length' && $r['source_type'] !== 'component';
            $pn = isset($skus[$r['product_id']]) ? $this->basePn($skus[$r['product_id']]) : '(unresolved:'.$r['part_label'].')';
            $inches = round((float) $r['calculated_length'], 3);
            $lines[] = [
                'pn' => $pn,
                'len' => $isStockCut ? round((float) $r['calculated_length'], 3) : null,
                'qty' => $isStockCut || $r['unit_type'] === 'qty'
                    ? (float) $r['quantity']
                    : (in_array($pn, $lfPns, true) ? (float) ceil($inches / 12) : $inches), // length component: inches (or feet if fab_utils reports LF)
            ];
        }

        return $this->aggregate($lines);
    }

    /** fab_utils' door output keeps kit PNs; ForgeDesk stocks the pieces, so expand the reference the same way. */
    private function expandKitLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $kit = \App\Services\Configurator\KitExpander::KITS[$l['pn']] ?? null;
            if (! $kit) {
                $out[] = $l;

                continue;
            }
            foreach ($kit as $piece) {
                $out[] = ['pn' => $piece['pn'], 'len' => null, 'qty' => $l['qty'] * $piece['qty']];
            }
        }

        return $out;
    }

    private function aggregate(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $key = $l['pn'].'|'.($l['len'] === null ? '-' : $l['len']);
            $out[$key] = ($out[$key] ?? 0) + $l['qty'];
        }
        ksort($out);

        return $out;
    }

    private function diff(array $expected, array $actual): array
    {
        $diffs = [];
        foreach ($expected as $key => $qty) {
            if (! array_key_exists($key, $actual)) {
                $diffs[] = "missing  {$key} x{$qty}";
            } elseif (abs($actual[$key] - $qty) > 0.001) {
                $diffs[] = "qty      {$key} fab_utils={$qty} forgedesk={$actual[$key]}";
            }
        }
        foreach ($actual as $key => $qty) {
            if (! array_key_exists($key, $expected)) {
                $diffs[] = "extra    {$key} x{$qty}";
            }
        }

        return $diffs;
    }
}
