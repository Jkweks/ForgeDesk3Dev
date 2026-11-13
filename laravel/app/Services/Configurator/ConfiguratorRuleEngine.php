<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorHwlibBacker;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\ConfiguratorRule;
use App\Models\DoorFrameConfiguration;
use App\Models\Product;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Applies the admin-defined configurator rules (Configurator Admin > Rules) to an opening.
 *
 * A rule is "when these conditions hold, do these actions". Rules run as a pass over the rows the
 * standard generators produce (FrameBomGenerator / DoorBomGenerator / HwlibBomGenerator call
 * apply() last), so the catalog keeps working unchanged and a rule only describes the exception.
 *
 * Conditions: {logic: all|any, items: [condition | group]}; a condition is one of
 *  - {type: hardware, by: item|pn|category, value, leaf?, present: bool}  hardware on the opening
 *  - {type: function, value: CODE, present: bool}                         a selected hardware function
 *  - {type: field, field, op: eq|neq|in, value}                           an opening / frame / door field
 *  - {type: compare, left: expr, op: eq|neq|gt|gte|lt|lte, right: expr}   two expressions
 *
 * Actions (each names a scope: frame | door | hardware, except set_variable and message):
 *  - add_part      {scope, pn, label?, qty?, length?, source_type?}   qty is per opening
 *  - remove_part   {scope, match}
 *  - swap_part     {scope, match, pn}
 *  - split_part    {scope, match, pieces: [{label, length}]}          one row per piece
 *  - adjust_part   {scope, match, length?, qty?}
 *  - set_variable  {code, value}                                      a hardware prep variable
 *  - message       {severity: warning|error, text}                    error blocks release
 * `match` is {by: label|pn, op: eq|contains, value}.
 *
 * Expressions (see RuleExpression) can read: W, H, TH, THRESHOLD (0 without one), HAS_THRESHOLD,
 * HAS_TRANSOM, IS_PAIR, QTY, any hardware variable code (PANIC_CL_AFF…), a backer's length as
 * P2451.length or length('P2451'), a frame role's section height as section('Lock Jamb Stop'), and
 * inside part actions ORIGINAL / ORIGINAL_QTY (the matched row) and, in a split, PIECE1… and
 * REMAINDER (ORIGINAL less the pieces so far).
 *
 * A value that cannot be resolved skips that one action with a warning — never a guessed cut.
 * Conditions and set_variable values are computed from the opening as it is before any rule runs.
 */
class ConfiguratorRuleEngine
{
    public const SCOPES = ['frame' => 'Frame BOM', 'door' => 'Door BOM', 'hardware' => 'Hardware BOM'];

    public const ACTIONS = [
        'add_part' => 'Add a part',
        'remove_part' => 'Remove a part',
        'swap_part' => 'Swap a part',
        'split_part' => 'Split a part into pieces',
        'adjust_part' => 'Change a part\'s length / quantity',
        'set_variable' => 'Set a prep value',
        'message' => 'Show a warning / block release',
    ];

    /** Actions that change BOM rows (and so carry a scope). */
    private const PART_ACTIONS = ['add_part', 'remove_part', 'swap_part', 'split_part', 'adjust_part'];

    public const FIELDS = [
        'opening_type' => 'Opening type',
        'handing' => 'Handing',
        'hinging' => 'Hinging',
        'finish' => 'Finish',
        'job_scope' => 'Scope (door / frame)',
        'has_threshold' => 'Has threshold',
        'has_transom' => 'Has transom',
        'frame_system' => 'Frame system',
        'frame_series' => 'Frame series (code)',
        'door_series' => 'Door series',
        'stile_width' => 'Door stile width',
    ];

    public const BUILT_INS = [
        'W' => 'Door opening width',
        'H' => 'Door opening height',
        'TH' => 'Total frame height',
        'THRESHOLD' => 'Threshold height (0 without a threshold)',
        'HAS_THRESHOLD' => '1 with a threshold, else 0',
        'HAS_TRANSOM' => '1 with a transom, else 0',
        'IS_PAIR' => '1 for a pair, else 0',
        'QTY' => 'Number of openings on this entry',
    ];

    private const COMPARE_OPS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'];

    /** @var array<int, ?string> product id => part number */
    private array $partNumbers = [];

    public function __construct(private FinishFallbackResolver $finishResolver = new FinishFallbackResolver) {}

    // ------------------------------------------------------------------ entry points

    /**
     * Runs the part actions of every rule that fires, for one BOM scope.
     *
     * @param  array<int, array>  $rows  generator rows
     * @param  array<int, string>  $warnings  appended to when an action has to be skipped
     * @param  array|null  $frameDims  FrameBomGenerator::dimensions(), when the caller already has it
     * @return array<int, array>
     */
    public function apply(string $scope, DoorFrameConfiguration $config, array $rows, array &$warnings = [], ?array $frameDims = null): array
    {
        // Unsaved configurations (the parity check builds them in memory) have no hardware to react to.
        if (! $config->exists) {
            return $rows;
        }
        $rules = $this->enabledRules()->filter(fn ($r) => $this->actionsFor($r, $scope) !== []);
        if ($rules->isEmpty()) {
            return $rows;
        }

        $run = $this->evaluate($config, $rules, $frameDims);
        $changed = false;
        foreach ($run['fired'] as [$rule, $triggerLinkId]) {
            foreach ($this->actionsFor($rule, $scope) as $action) {
                try {
                    $rows = $this->applyAction($action, $scope, $rows, $rule, $triggerLinkId, $run['ctx']);
                    $changed = true;
                } catch (RuntimeException $e) {
                    $warnings[] = "Rule \"{$rule->name}\": ".strtolower(self::ACTIONS[$action['type']] ?? 'action').' skipped — '.$e->getMessage().'.';
                }
            }
        }
        if ($changed) {
            foreach ($rows as $i => &$row) {
                $row['sort_order'] = $i;
            }
        }

        return $rows;
    }

    /**
     * Prep-variable values set by rules, for HwlibResolver.
     *
     * @return array<string, array{value: string, rule: string}> variable code => value
     */
    public function variableOverrides(DoorFrameConfiguration $config): array
    {
        if (! $config->exists) {
            return [];
        }
        $rules = $this->enabledRules()->filter(
            fn ($r) => collect($r->actions ?? [])->contains(fn ($a) => ($a['type'] ?? '') === 'set_variable')
        );

        return $rules->isEmpty() ? [] : $this->evaluate($config, $rules)['overrides'];
    }

    /**
     * What the rules currently say about an opening: which fire, their messages, and a signature
     * per BOM scope to compare with the one stored when that BOM was generated.
     *
     * @return array{fired: array<int, array{id: int, name: string, scopes: array<int, string>}>, messages: array<int, array{rule: string, severity: string, text: string}>, signatures: array<string, ?string>}
     */
    public function status(DoorFrameConfiguration $config): array
    {
        $empty = ['fired' => [], 'messages' => [], 'signatures' => array_fill_keys(array_keys(self::SCOPES), null)];
        $rules = $config->exists ? $this->enabledRules() : collect();
        if ($rules->isEmpty()) {
            return $empty;
        }

        $run = $this->evaluate($config, $rules);
        $messages = $run['messages'];
        $fired = [];
        $signatures = $empty['signatures'];
        $parts = array_fill_keys(array_keys(self::SCOPES), []);

        foreach ($run['fired'] as [$rule]) {
            $scopes = [];
            foreach (array_keys(self::SCOPES) as $scope) {
                $actions = $this->actionsFor($rule, $scope);
                if (! $actions) {
                    continue;
                }
                $scopes[] = $scope;
                $inputs = [];
                foreach ($this->actionExpressions($actions) as $expr) {
                    foreach (RuleExpression::identifiers($expr) as $name) {
                        if ($this->isLocalName($name)) {
                            continue;
                        }
                        $value = $this->identValue($name, $run['ctx']);
                        $inputs[$name] = $value === null ? null : round($value, 4);
                        if ($value === null) {
                            $messages[] = ['rule' => $rule->name, 'severity' => 'warning', 'text' => "{$name} has no value yet, so this rule's ".strtolower(self::SCOPES[$scope]).' changes are skipped.'];
                        }
                    }
                }
                ksort($inputs);
                $parts[$scope][] = [$rule->id, $rule->updated_at?->timestamp, $inputs];
            }
            $fired[] = ['id' => $rule->id, 'name' => $rule->name, 'scopes' => $scopes];
        }
        foreach ($parts as $scope => $entries) {
            $signatures[$scope] = $entries ? md5(json_encode($entries)) : null;
        }

        return ['fired' => $fired, 'messages' => array_values(array_unique($messages, SORT_REGULAR)), 'signatures' => $signatures];
    }

    /** Signature of one scope, stored on the configuration when that BOM is generated. */
    public function signature(DoorFrameConfiguration $config, string $scope): ?string
    {
        return $this->status($config)['signatures'][$scope] ?? null;
    }

    // ------------------------------------------------------------------ evaluation

    private function enabledRules(): Collection
    {
        return ConfiguratorRule::where('enabled', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    private function actionsFor(ConfiguratorRule $rule, string $scope): array
    {
        return array_values(array_filter($rule->actions ?? [], fn ($a) => in_array($a['type'] ?? '', self::PART_ACTIONS, true) && ($a['scope'] ?? '') === $scope));
    }

    /**
     * @return array{ctx: array, fired: array<int, array{0: ConfiguratorRule, 1: ?int}>, overrides: array, messages: array}
     */
    private function evaluate(DoorFrameConfiguration $config, Collection $rules, ?array $frameDims = null): array
    {
        $ctx = $this->context($config, $frameDims);
        $fired = [];
        $overrides = [];
        $messages = [];

        foreach ($rules as $rule) {
            $trigger = null;
            if (! $this->matchGroup($rule->conditions, $ctx, $trigger)) {
                continue;
            }
            $fired[] = [$rule, $trigger ?? $ctx['first_link_id']];

            foreach ($rule->actions ?? [] as $action) {
                if (($action['type'] ?? '') === 'message' && trim((string) ($action['text'] ?? '')) !== '') {
                    $messages[] = ['rule' => $rule->name, 'severity' => ($action['severity'] ?? 'warning') === 'error' ? 'error' : 'warning', 'text' => trim($action['text'])];
                }
                if (($action['type'] ?? '') === 'set_variable' && ($code = strtoupper(trim((string) ($action['code'] ?? '')))) !== '') {
                    try {
                        $value = $this->expr((string) ($action['value'] ?? ''), $ctx);
                        $overrides[$code] = ['value' => $this->num($value), 'rule' => $rule->name];
                    } catch (RuntimeException $e) {
                        $messages[] = ['rule' => $rule->name, 'severity' => 'warning', 'text' => "{$code} was not set — ".$e->getMessage().'.'];
                    }
                }
            }
        }

        // Part actions see the values the rules set (and anything calculated from them).
        $ctx['resolver']?->setRuleOverrides($overrides);

        return ['ctx' => $ctx, 'fired' => $fired, 'overrides' => $overrides, 'messages' => $messages];
    }

    private function context(DoorFrameConfiguration $config, ?array $frameDims): array
    {
        $opening = $config->openingSpecs;
        $frame = $config->frameConfig;
        $door = $config->doorConfigs()->first();
        $links = $config->hardwareLinks()->with(['item.category', 'functions'])->get();

        if ($frameDims === null && $frame?->frame_series_id && $opening) {
            try {
                $frameDims = app(FrameBomGenerator::class)->dimensions($config);
            } catch (RuntimeException) {
                $frameDims = null;
            }
        }
        $sections = $frameDims['section_heights'] ?? [];
        $hasThreshold = (bool) ($frame->has_threshold ?? false);
        $hasTransom = (bool) ($frame->has_transom ?? false);
        $isPair = ($opening->opening_type ?? null) === 'pair';
        $quantity = max(1, (int) $config->quantity);

        $numbers = [
            'HAS_THRESHOLD' => (float) $hasThreshold,
            'HAS_TRANSOM' => (float) $hasTransom,
            'IS_PAIR' => (float) $isPair,
            'QTY' => (float) $quantity,
            'THRESHOLD' => $hasThreshold ? (float) ($sections['Threshold'] ?? 0) : 0.0,
        ];
        if ($opening) {
            $numbers['W'] = (float) $opening->door_opening_width;
            $numbers['H'] = (float) $opening->door_opening_height;
        }
        if ($frameDims) {
            $numbers['TH'] = (float) $frameDims['context']['total_height'];
        }

        $series = $frameDims['series'] ?? null;

        return [
            'config' => $config,
            'links' => $links,
            'first_link_id' => $links->first()?->id,
            'resolver' => $links->isNotEmpty() ? new HwlibResolver($config, false, []) : null,
            'numbers' => $numbers,
            'sections' => $sections,
            'finish' => strtoupper((string) ($opening->finish ?? '')),
            'qty' => [
                'frame' => $frameDims['opening_qty'] ?? ($isPair ? max(1, (int) ceil($quantity / 2)) : $quantity),
                'door' => $quantity,
                'hardware' => $quantity,
            ],
            'fields' => [
                'opening_type' => $opening->opening_type ?? null,
                'handing' => $opening?->deriveDoorHanding(),
                'hinging' => $opening->hinging ?? null,
                'finish' => $opening->finish ?? null,
                'job_scope' => $config->job_scope,
                'has_threshold' => $hasThreshold ? 'yes' : 'no',
                'has_transom' => $hasTransom ? 'yes' : 'no',
                'frame_system' => $series?->frameSystem?->name,
                'frame_series' => $series?->code,
                'door_series' => $door->door_series ?? null,
                'stile_width' => $door->stile_width ?? null,
            ],
        ];
    }

    // ------------------------------------------------------------------ conditions

    /** @param ?int $trigger set to the first hardware link a positive hardware condition matched */
    private function matchGroup(?array $group, array $ctx, ?int &$trigger): bool
    {
        $items = $group['items'] ?? [];
        if (! $items) {
            return true;
        }
        $any = ($group['logic'] ?? 'all') === 'any';
        foreach ($items as $item) {
            $hit = isset($item['items']) ? $this->matchGroup($item, $ctx, $trigger) : $this->matchCondition($item, $ctx, $trigger);
            if ($any && $hit) {
                return true;
            }
            if (! $any && ! $hit) {
                return false;
            }
        }

        return ! $any;
    }

    private function matchCondition(array $c, array $ctx, ?int &$trigger): bool
    {
        $same = fn ($a, $b) => mb_strtoupper(trim((string) $a)) === mb_strtoupper(trim((string) $b));
        $present = ! array_key_exists('present', $c) || filter_var($c['present'], FILTER_VALIDATE_BOOLEAN);

        switch ($c['type'] ?? '') {
            case 'hardware':
                $by = $c['by'] ?? 'pn';
                $value = $c['value'] ?? '';
                $leaf = $c['leaf'] ?? '';
                $link = $ctx['links']->first(function ($l) use ($by, $value, $leaf, $same) {
                    if ($leaf !== '' && $leaf !== null && ! in_array($l->leaf, [$leaf, 'both'], true)) {
                        return false;
                    }

                    return match ($by) {
                        'item' => (int) $l->item_id === (int) $value,
                        'category' => $same($l->item->category->name ?? '', $value),
                        default => $l->item->pn !== null && $same($l->item->pn, $value),
                    };
                });
                if ($link && $present) {
                    $trigger ??= $link->id;
                }

                return ($link !== null) === $present;

            case 'function':
                $found = $ctx['links']->contains(fn ($l) => $l->functions->contains(fn ($f) => $same($f->code, $c['value'] ?? '')));

                return $found === $present;

            case 'field':
                $actual = $ctx['fields'][$c['field'] ?? ''] ?? null;
                $value = (string) ($c['value'] ?? '');

                return match ($c['op'] ?? 'eq') {
                    'neq' => ! $same($actual, $value),
                    'in' => collect(explode(',', $value))->contains(fn ($v) => trim($v) !== '' && $same($actual, $v)),
                    default => $same($actual, $value),
                };

            case 'compare':
                try {
                    $left = $this->expr((string) ($c['left'] ?? ''), $ctx);
                    $right = $this->expr((string) ($c['right'] ?? ''), $ctx);
                } catch (RuntimeException) {
                    return false; // a value that is not there cannot satisfy a comparison
                }

                return match ($c['op'] ?? 'eq') {
                    'neq' => abs($left - $right) >= 1e-9,
                    'gt' => $left > $right + 1e-9,
                    'gte' => $left >= $right - 1e-9,
                    'lt' => $left < $right - 1e-9,
                    'lte' => $left <= $right + 1e-9,
                    default => abs($left - $right) < 1e-9,
                };
        }

        return false;
    }

    // ------------------------------------------------------------------ expressions

    private function expr(string $expr, array $ctx, array $locals = []): float
    {
        return RuleExpression::evaluate(
            $expr,
            fn (string $name) => $locals[$name] ?? $this->identValue($name, $ctx),
            fn (string $fn, array $args) => match ($fn) {
                'section' => isset($ctx['sections'][(string) ($args[0] ?? '')]) ? (float) $ctx['sections'][(string) $args[0]] : null,
                'length' => $this->backerLength((string) ($args[0] ?? '')),
                default => null,
            }
        );
    }

    private function isLocalName(string $name): bool
    {
        return in_array($name, ['ORIGINAL', 'ORIGINAL_QTY', 'REMAINDER'], true) || preg_match('/^PIECE\d+$/', $name) === 1;
    }

    private function identValue(string $name, array $ctx): ?float
    {
        if (array_key_exists($name, $ctx['numbers'])) {
            return $ctx['numbers'][$name];
        }
        if (isset(self::BUILT_INS[$name])) {
            return null; // a built-in this opening has no value for (e.g. TH without a frame)
        }
        if (str_contains($name, '.')) {
            [$pn, $property] = explode('.', $name, 2);

            return strtolower($property) === 'length' ? $this->backerLength($pn) : null;
        }

        $code = strtoupper($name);
        if ($ctx['resolver']) {
            $seen = [];

            return $this->toNumber($ctx['resolver']->resolveVar($code, $ctx['first_link_id'], $seen)['value']);
        }

        return $this->toNumber(ConfiguratorHwlibVariable::where('code', $code)->value('default_value'));
    }

    private function backerLength(string $pn): ?float
    {
        $length = ConfiguratorHwlibBacker::whereRaw('upper(pn) = ?', [strtoupper(trim($pn))])->value('length');

        return $length !== null ? (float) $length : null;
    }

    private function toNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if (preg_match('#^(-?\d+(?:\.\d+)?)/(\d+(?:\.\d+)?)$#', $s, $m)) {
            return (float) $m[2] != 0.0 ? (float) $m[1] / (float) $m[2] : null;
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    // ------------------------------------------------------------------ part actions

    /** All-or-nothing: throws before changing anything when a value cannot be resolved. */
    private function applyAction(array $action, string $scope, array $rows, ConfiguratorRule $rule, ?int $triggerLinkId, array $ctx): array
    {
        $type = $action['type'];
        $matches = $type === 'add_part' ? [] : $this->matchingRows($rows, $action['match'] ?? []);
        $hasLengths = $scope !== 'hardware';

        switch ($type) {
            case 'add_part':
                $product = $this->product((string) ($action['pn'] ?? ''), $ctx['finish']);
                $qty = $this->expr(trim((string) ($action['qty'] ?? '')) ?: '1', $ctx) * $ctx['qty'][$scope];
                if ($qty <= 0) {
                    throw new RuntimeException('the quantity works out to '.$this->num($qty));
                }
                $length = $hasLengths && trim((string) ($action['length'] ?? '')) !== '' ? $this->positive($this->expr($action['length'], $ctx), 'the length') : null;
                $label = trim((string) ($action['label'] ?? '')) ?: ($product->description ?: $product->part_number);

                $row = ['part_label' => $label, 'product_id' => $product->id, 'quantity' => round($qty, 3), 'is_auto_generated' => true, 'rule_id' => $rule->id];
                if ($scope === 'hardware') {
                    $sourceType = in_array($action['source_type'] ?? '', ['item', 'backer', 'fastener'], true) ? $action['source_type'] : 'backer';
                    $row += ['source_type' => $sourceType, 'hwlib_link_id' => $triggerLinkId];
                } else {
                    $row += [
                        'calculated_length' => $length,
                        'unit_type' => $length !== null ? 'length' : 'qty',
                        'source_type' => $length === null ? 'component' : ($scope === 'frame' ? 'profile' : 'extrusion'),
                    ];
                }
                $rows[] = $row;

                return $rows;

            case 'remove_part':
                return array_values(array_diff_key($rows, array_flip($matches)));

            case 'swap_part':
                $product = $this->product((string) ($action['pn'] ?? ''), $ctx['finish']);
                foreach ($matches as $i) {
                    $rows[$i]['product_id'] = $product->id;
                    $rows[$i]['rule_id'] = $rule->id;
                }

                return $rows;

            case 'adjust_part':
                $updates = [];
                foreach ($matches as $i) {
                    $locals = ['ORIGINAL' => (float) ($rows[$i]['calculated_length'] ?? 0), 'ORIGINAL_QTY' => (float) ($rows[$i]['quantity'] ?? 0)];
                    $update = ['rule_id' => $rule->id];
                    if ($hasLengths && trim((string) ($action['length'] ?? '')) !== '') {
                        if (($rows[$i]['calculated_length'] ?? null) === null) {
                            throw new RuntimeException("\"{$rows[$i]['part_label']}\" is not a cut-to-length part");
                        }
                        $update['calculated_length'] = $this->positive($this->expr($action['length'], $ctx, $locals), 'the length');
                    }
                    if (trim((string) ($action['qty'] ?? '')) !== '') {
                        $qty = $this->expr($action['qty'], $ctx, $locals);
                        if ($qty < 0) {
                            throw new RuntimeException('the quantity works out to '.$this->num($qty));
                        }
                        $update['quantity'] = round($qty, 3);
                    }
                    $updates[$i] = $update;
                }
                foreach ($updates as $i => $update) {
                    $rows[$i] = array_merge($rows[$i], $update);
                }

                return $rows;

            case 'split_part':
                if (! $hasLengths) {
                    throw new RuntimeException('hardware parts have no length to split');
                }
                $replacements = [];
                foreach ($matches as $i) {
                    $original = $rows[$i]['calculated_length'] ?? null;
                    if ($original === null) {
                        throw new RuntimeException("\"{$rows[$i]['part_label']}\" is not a cut-to-length part");
                    }
                    $locals = ['ORIGINAL' => (float) $original, 'ORIGINAL_QTY' => (float) ($rows[$i]['quantity'] ?? 0), 'REMAINDER' => (float) $original];
                    $pieces = [];
                    foreach (array_values($action['pieces'] ?? []) as $n => $piece) {
                        $name = trim((string) ($piece['label'] ?? '')) ?: 'Piece '.($n + 1);
                        $length = $this->positive($this->expr((string) ($piece['length'] ?? ''), $ctx, $locals), "piece \"{$name}\"");
                        $locals['PIECE'.($n + 1)] = $length;
                        $locals['REMAINDER'] -= $length;
                        $pieces[] = array_merge($rows[$i], [
                            'part_label' => "{$rows[$i]['part_label']} — {$name}",
                            'calculated_length' => $length,
                            'rule_id' => $rule->id,
                        ]);
                    }
                    if ($locals['REMAINDER'] < -1e-6) {
                        throw new RuntimeException("the pieces of \"{$rows[$i]['part_label']}\" add up to more than its ".$this->num((float) $original).'" length');
                    }
                    $replacements[$i] = $pieces;
                }
                $out = [];
                foreach ($rows as $i => $row) {
                    array_push($out, ...($replacements[$i] ?? [$row]));
                }

                return $out;
        }

        return $rows;
    }

    private function positive(float $value, string $what): float
    {
        if ($value <= 1e-6) {
            throw new RuntimeException("{$what} works out to ".$this->num($value).'"');
        }

        return round($value, 4);
    }

    /** @return array<int, int> indexes of the rows the match selects */
    private function matchingRows(array $rows, array $match): array
    {
        $value = mb_strtoupper(trim((string) ($match['value'] ?? '')));
        if ($value === '') {
            throw new RuntimeException('no part is named to match');
        }
        $by = $match['by'] ?? 'label';
        $contains = ($match['op'] ?? 'eq') === 'contains';

        if ($by === 'pn') {
            $missing = array_diff(array_filter(array_column($rows, 'product_id')), array_keys($this->partNumbers));
            if ($missing) {
                $this->partNumbers += Product::whereIn('id', $missing)->pluck('part_number', 'id')->all();
            }
        }

        $hits = [];
        foreach ($rows as $i => $row) {
            $actual = mb_strtoupper(trim((string) ($by === 'pn' ? ($this->partNumbers[$row['product_id'] ?? 0] ?? '') : ($row['part_label'] ?? ''))));
            if ($actual !== '' && ($contains ? str_contains($actual, $value) : $actual === $value)) {
                $hits[] = $i;
            }
        }

        return $hits;
    }

    /**
     * A part number in the opening's finish (walking the usual fallback chain), or exactly as typed
     * when it carries its own finish suffix (P797-0R) or is stocked without one.
     */
    private function product(string $pn, string $finish): Product
    {
        $pn = trim($pn);
        if ($pn === '') {
            throw new RuntimeException('no part number is given');
        }
        $product = null;
        if (preg_match('/^(.*)-(BL|C2|DB|0R)$/i', $pn, $m)) {
            $product = $this->finishResolver->resolve($m[1], strtoupper($m[2]));
        }
        $product ??= $this->finishResolver->resolve($pn, $finish !== '' ? $finish : '0R')
            ?? Product::where('part_number', $pn)->first();
        if (! $product) {
            throw new RuntimeException("no product has part number {$pn}");
        }

        return $product;
    }

    // ------------------------------------------------------------------ validation

    /** Every expression a list of actions evaluates. */
    private function actionExpressions(array $actions): array
    {
        $out = [];
        foreach ($actions as $a) {
            foreach (['qty', 'length', 'value'] as $key) {
                if (trim((string) ($a[$key] ?? '')) !== '' && ($a['type'] ?? '') !== 'message') {
                    $out[] = (string) $a[$key];
                }
            }
            foreach ($a['pieces'] ?? [] as $piece) {
                $out[] = (string) ($piece['length'] ?? '');
            }
        }

        return $out;
    }

    /** Checks a rule's shape before it is saved; returns the problems found. */
    public static function validate(?array $conditions, ?array $actions): array
    {
        $errors = [];
        $expr = function (mixed $value, string $where, bool $required = true) use (&$errors) {
            $value = trim((string) ($value ?? ''));
            if ($value === '') {
                if ($required) {
                    $errors[] = "{$where}: a value is needed.";
                }

                return;
            }
            if ($problem = RuleExpression::syntaxError($value)) {
                $errors[] = "{$where}: {$problem}.";
            }
        };

        $group = function (mixed $g, int $depth = 0) use (&$group, &$errors, $expr) {
            if ($g === null || $g === []) {
                return;
            }
            if (! is_array($g) || ! in_array($g['logic'] ?? 'all', ['all', 'any'], true) || ! is_array($g['items'] ?? []) || $depth > 4) {
                $errors[] = 'Conditions: malformed group.';

                return;
            }
            foreach ($g['items'] ?? [] as $n => $c) {
                $where = 'Condition '.($n + 1);
                if (! is_array($c)) {
                    $errors[] = "{$where}: malformed.";
                } elseif (isset($c['items'])) {
                    $group($c, $depth + 1);
                } else {
                    switch ($c['type'] ?? '') {
                        case 'hardware':
                            if (! in_array($c['by'] ?? '', ['item', 'pn', 'category'], true) || trim((string) ($c['value'] ?? '')) === '') {
                                $errors[] = "{$where}: choose the hardware to look for.";
                            }
                            break;
                        case 'function':
                            if (trim((string) ($c['value'] ?? '')) === '') {
                                $errors[] = "{$where}: choose a function.";
                            }
                            break;
                        case 'field':
                            if (! isset(self::FIELDS[$c['field'] ?? '']) || ! in_array($c['op'] ?? 'eq', ['eq', 'neq', 'in'], true)) {
                                $errors[] = "{$where}: unknown field or comparison.";
                            }
                            break;
                        case 'compare':
                            if (! in_array($c['op'] ?? '', self::COMPARE_OPS, true)) {
                                $errors[] = "{$where}: unknown comparison.";
                            }
                            $expr($c['left'] ?? '', "{$where} (left side)");
                            $expr($c['right'] ?? '', "{$where} (right side)");
                            break;
                        default:
                            $errors[] = "{$where}: unknown condition type.";
                    }
                }
            }
        };
        $group($conditions);

        if (! $actions) {
            $errors[] = 'A rule needs at least one action.';
        }
        foreach (array_values($actions ?? []) as $n => $a) {
            $where = 'Action '.($n + 1);
            $type = is_array($a) ? ($a['type'] ?? '') : '';
            if (! isset(self::ACTIONS[$type])) {
                $errors[] = "{$where}: unknown action.";

                continue;
            }
            if (in_array($type, self::PART_ACTIONS, true)) {
                if (! isset(self::SCOPES[$a['scope'] ?? ''])) {
                    $errors[] = "{$where}: choose which BOM it changes.";
                }
                if ($type !== 'add_part' && (! in_array($a['match']['by'] ?? '', ['label', 'pn'], true) || trim((string) ($a['match']['value'] ?? '')) === '')) {
                    $errors[] = "{$where}: say which part it applies to.";
                }
            }
            switch ($type) {
                case 'add_part':
                    if (trim((string) ($a['pn'] ?? '')) === '') {
                        $errors[] = "{$where}: a part number is needed.";
                    }
                    $expr($a['qty'] ?? '', "{$where} quantity", false);
                    $expr($a['length'] ?? '', "{$where} length", false);
                    break;
                case 'swap_part':
                    if (trim((string) ($a['pn'] ?? '')) === '') {
                        $errors[] = "{$where}: a part number is needed.";
                    }
                    break;
                case 'adjust_part':
                    if (trim((string) ($a['qty'] ?? '')) === '' && trim((string) ($a['length'] ?? '')) === '') {
                        $errors[] = "{$where}: give a new length or quantity.";
                    }
                    $expr($a['qty'] ?? '', "{$where} quantity", false);
                    $expr($a['length'] ?? '', "{$where} length", false);
                    break;
                case 'split_part':
                    if (($a['scope'] ?? '') === 'hardware') {
                        $errors[] = "{$where}: only frame and door parts can be split.";
                    }
                    if (count($a['pieces'] ?? []) < 2) {
                        $errors[] = "{$where}: a split needs at least two pieces.";
                    }
                    foreach (array_values($a['pieces'] ?? []) as $p => $piece) {
                        $expr($piece['length'] ?? '', "{$where} piece ".($p + 1).' length');
                    }
                    break;
                case 'set_variable':
                    if (! ConfiguratorHwlibVariable::where('code', strtoupper(trim((string) ($a['code'] ?? ''))))->exists()) {
                        $errors[] = "{$where}: unknown prep variable.";
                    }
                    $expr($a['value'] ?? '', "{$where} value");
                    break;
                case 'message':
                    if (trim((string) ($a['text'] ?? '')) === '') {
                        $errors[] = "{$where}: the message is empty.";
                    }
                    break;
            }
        }

        return array_values(array_unique($errors));
    }
}
