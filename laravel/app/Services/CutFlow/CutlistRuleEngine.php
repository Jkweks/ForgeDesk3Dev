<?php

namespace App\Services\CutFlow;

use App\Models\CutlistRule;
use App\Support\Dimension;
use Illuminate\Support\Collection;

/**
 * Runs the admin-defined integrity rules (Development > Cut-list rules) against an uploaded cut list.
 *
 * Rows are the raw CutlistIngestService::parseCsv() rows, normalised to the fields below and grouped into
 * elevations (project | work order | elevation tag — the same grouping the sill report uses).
 *
 * Rule kinds (definition JSON):
 *  - row:       {filter, require}  every row matching `filter` must also match `require`.
 *  - count:     {when, filter, metric, field, op, value}  per elevation (only those containing a row matching
 *               `when`; empty = all), the metric over the rows matching `filter` must satisfy op/value.
 *  - companion: {filter, partner, mode, same_bay}  per row matching `filter`, a partner row matching `partner`
 *               must exist (mode=require) or must not exist (mode=forbid) in the same elevation / bay.
 * A group is {logic: all|any, items: [condition|group]}; a condition is {field, op, value}.
 */
class CutlistRuleEngine
{
    public const FIELDS = [
        'part_number' => ['label' => 'Part number', 'type' => 'text'],
        'finish' => ['label' => 'Finish', 'type' => 'text'],
        'use' => ['label' => 'Use (description, tags removed)', 'type' => 'text'],
        'tags' => ['label' => 'Tags ({...} in description)', 'type' => 'text'],
        'description' => ['label' => 'Description (raw)', 'type' => 'text'],
        'length' => ['label' => 'Length (in)', 'type' => 'number'],
        'qty' => ['label' => 'Quantity', 'type' => 'number'],
        'bay' => ['label' => 'Bay / opening row', 'type' => 'number'],
        'column' => ['label' => 'Opening column', 'type' => 'number'],
        'project' => ['label' => 'Project / job', 'type' => 'text'],
        'work_order' => ['label' => 'Work order', 'type' => 'text'],
        'elevation' => ['label' => 'Elevation / door tag', 'type' => 'text'],
    ];

    public const OPS = [
        'eq' => 'equals', 'neq' => 'does not equal', 'contains' => 'contains', 'not_contains' => 'does not contain',
        'starts_with' => 'starts with', 'ends_with' => 'ends with', 'in' => 'is one of (comma list)', 'not_in' => 'is not one of',
        'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'between' => 'between (a,b)',
        'empty' => 'is empty', 'not_empty' => 'is not empty', 'regex' => 'matches regex',
    ];

    public const METRICS = [
        'count' => 'number of rows', 'sum_qty' => 'total quantity', 'sum' => 'sum of field',
        'min' => 'smallest value of field', 'max' => 'largest value of field', 'distinct' => 'distinct values of field',
    ];

    public const COMPARE_OPS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'];

    /** Findings reported per rule before the rest are summarised. */
    private const MAX_PER_RULE = 25;

    /** Raw parseCsv() rows -> normalised rule rows. */
    public function rowsFromRaw(array $rawRows): array
    {
        $rows = [];
        foreach ($rawRows as $data) {
            $desc = trim((string) ($data['description'] ?? ''));
            preg_match_all('/\{[^}]*\}/', $desc, $m);
            $use = strtoupper(trim(preg_replace('/\s+/', ' ', preg_replace('/\{[^}]*\}/', '', $desc)), ' ,'));
            $length = trim((string) ($data['dimension_in'] ?? $data['dimension'] ?? ''));
            $bay = trim((string) ($data['row'] ?? ''));
            $col = trim((string) ($data['column'] ?? ''));
            $rows[] = [
                'part_number' => trim((string) ($data['part_id'] ?? $data['name'] ?? '')),
                'finish' => trim((string) ($data['finish'] ?? '')),
                'use' => $use,
                'tags' => strtoupper(implode(' ', $m[0])),
                'description' => $desc,
                'length' => $length === '' ? null : Dimension::parse($length),
                'qty' => is_numeric($data['qty'] ?? null) ? (float) $data['qty'] : null,
                'bay' => is_numeric($bay) ? (float) $bay : null,
                'column' => is_numeric($col) ? (float) $col : null,
                'project' => trim((string) ($data['job'] ?? '')),
                'work_order' => trim((string) ($data['work_order'] ?? '')),
                'elevation' => trim((string) ($data['phase'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param  array  $rows  normalised rows (rowsFromRaw)
     * @param  Collection<int, CutlistRule>|null  $rules  default: every enabled rule
     * @return array<int, array{rule_id: ?int, rule: string, severity: string, scope: string, message: string}>
     */
    public function evaluate(array $rows, ?Collection $rules = null): array
    {
        $rules ??= CutlistRule::where('enabled', true)->orderBy('sort_order')->orderBy('id')->get();

        $elevations = [];
        foreach ($rows as $r) {
            $elevations[$r['project'].'|'.$r['work_order'].'|'.$r['elevation']][] = $r;
        }
        uksort($elevations, 'strnatcasecmp');

        $findings = [];
        foreach ($rules as $rule) {
            $found = match ($rule->kind) {
                'row' => $this->runRow($rule, $elevations),
                'count' => $this->runCount($rule, $elevations),
                'companion' => $this->runCompanion($rule, $elevations),
                default => [],
            };
            $extra = count($found) - self::MAX_PER_RULE;
            foreach (array_slice($found, 0, self::MAX_PER_RULE) as [$scope, $msg]) {
                $findings[] = ['rule_id' => $rule->id, 'rule' => $rule->name, 'severity' => $rule->severity, 'scope' => $scope, 'message' => $msg];
            }
            if ($extra > 0) {
                $findings[] = ['rule_id' => $rule->id, 'rule' => $rule->name, 'severity' => $rule->severity, 'scope' => '', 'message' => "…and {$extra} more."];
            }
        }

        return $findings;
    }

    /** One line per finding, for flash messages. */
    public static function describe(array $f): string
    {
        return trim(($f['scope'] !== '' ? $f['scope'].': ' : '').$f['message']);
    }

    // ---------------------------------------------------------------- rule kinds

    private function runRow(CutlistRule $rule, array $elevations): array
    {
        $d = $rule->definition;
        $out = [];
        foreach ($elevations as $key => $rows) {
            foreach ($rows as $r) {
                if ($this->matchGroup($d['filter'] ?? null, $r) && ! $this->matchGroup($d['require'] ?? null, $r)) {
                    $out[] = [$this->label($key), $this->message($rule, $r, 'Row does not meet the rule')];
                }
            }
        }

        return $out;
    }

    private function runCompanion(CutlistRule $rule, array $elevations): array
    {
        $d = $rule->definition;
        $forbid = ($d['mode'] ?? 'require') === 'forbid';
        $sameBay = (bool) ($d['same_bay'] ?? false);
        $out = [];
        foreach ($elevations as $key => $rows) {
            foreach ($rows as $i => $r) {
                if (! $this->matchGroup($d['filter'] ?? null, $r)) {
                    continue;
                }
                $found = false;
                foreach ($rows as $j => $p) {
                    if ($i !== $j && (! $sameBay || ($p['bay'] === $r['bay'])) && $this->matchGroup($d['partner'] ?? null, $p)) {
                        $found = true;
                        break;
                    }
                }
                if ($found === $forbid) {
                    $out[] = [$this->label($key), $this->message($rule, $r, $forbid ? 'A row that must not be present was found' : 'Required companion row is missing')];
                }
            }
        }

        return $out;
    }

    private function runCount(CutlistRule $rule, array $elevations): array
    {
        $d = $rule->definition;
        $metric = $d['metric'] ?? 'count';
        $field = $d['field'] ?? 'length';
        $op = $d['op'] ?? 'eq';
        $target = $d['value'] ?? 0;
        $out = [];
        foreach ($elevations as $key => $rows) {
            $when = $d['when'] ?? null;
            if ($this->hasItems($when) && ! collect($rows)->contains(fn ($r) => $this->matchGroup($when, $r))) {
                continue;
            }
            $sel = array_values(array_filter($rows, fn ($r) => $this->matchGroup($d['filter'] ?? null, $r)));
            $actual = $this->metric($metric, $field, $sel);
            if ($actual === null) {
                if ($op === 'empty') {
                    continue;
                }
                $actual = 0.0;
            }
            if (! $this->compare($actual, $op, $target)) {
                $out[] = [$this->label($key), $this->message($rule, [], 'Found '.$this->num($actual).' ('.(self::METRICS[$metric] ?? $metric).'), expected '.(self::OPS[$op] ?? $op).' '.$target, ['actual' => $this->num($actual), 'value' => (string) $target])];
            }
        }

        return $out;
    }

    private function metric(string $metric, string $field, array $sel): ?float
    {
        $vals = fn () => array_values(array_filter(array_map(fn ($r) => $r[$field] ?? null, $sel), fn ($v) => $v !== null && $v !== ''));
        $nums = fn () => array_map('floatval', array_filter($vals(), 'is_numeric'));

        return match ($metric) {
            'count' => (float) count($sel),
            'sum_qty' => (float) array_sum(array_map(fn ($r) => $r['qty'] ?? 0, $sel)),
            'sum' => (float) array_sum($nums()),
            'min' => $nums() ? (float) min($nums()) : null,
            'max' => $nums() ? (float) max($nums()) : null,
            'distinct' => (float) count(array_unique(array_map(fn ($v) => strtoupper((string) $v), $vals()))),
            default => null,
        };
    }

    private function compare(float $actual, string $op, mixed $target): bool
    {
        $t = (float) $target;

        return match ($op) {
            'eq' => abs($actual - $t) < 1e-9,
            'neq' => abs($actual - $t) >= 1e-9,
            'gt' => $actual > $t,
            'gte' => $actual >= $t - 1e-9,
            'lt' => $actual < $t,
            'lte' => $actual <= $t + 1e-9,
            default => true,
        };
    }

    // ---------------------------------------------------------------- conditions

    private function hasItems(?array $group): bool
    {
        return is_array($group) && ! empty($group['items']);
    }

    public function matchGroup(?array $group, array $row): bool
    {
        if (! $this->hasItems($group)) {
            return true;
        }
        $any = ($group['logic'] ?? 'all') === 'any';
        foreach ($group['items'] as $item) {
            $hit = isset($item['items']) ? $this->matchGroup($item, $row) : $this->matchCondition($item, $row);
            if ($any && $hit) {
                return true;
            }
            if (! $any && ! $hit) {
                return false;
            }
        }

        return ! $any;
    }

    private function matchCondition(array $c, array $row): bool
    {
        $field = $c['field'] ?? '';
        $op = $c['op'] ?? 'eq';
        $raw = $row[$field] ?? null;
        $value = (string) ($c['value'] ?? '');
        $isEmpty = $raw === null || trim((string) $raw) === '';

        if ($op === 'empty') {
            return $isEmpty;
        }
        if ($op === 'not_empty') {
            return ! $isEmpty;
        }
        if ($isEmpty) {
            return in_array($op, ['neq', 'not_contains', 'not_in'], true);
        }

        $numeric = (self::FIELDS[$field]['type'] ?? 'text') === 'number';
        $lhs = $numeric ? (float) $raw : mb_strtoupper(trim((string) $raw));
        $list = fn () => array_values(array_filter(array_map('trim', explode(',', $value)), fn ($s) => $s !== ''));
        $cmp = fn ($a, $b) => $numeric ? abs((float) $a - (float) $b) < 1e-9 : mb_strtoupper(trim((string) $a)) === mb_strtoupper(trim((string) $b));

        switch ($op) {
            case 'eq': return $cmp($lhs, $value);
            case 'neq': return ! $cmp($lhs, $value);
            case 'contains': return $value !== '' && str_contains(mb_strtoupper((string) $raw), mb_strtoupper($value));
            case 'not_contains': return $value === '' || ! str_contains(mb_strtoupper((string) $raw), mb_strtoupper($value));
            case 'starts_with': return str_starts_with(mb_strtoupper((string) $raw), mb_strtoupper($value));
            case 'ends_with': return str_ends_with(mb_strtoupper((string) $raw), mb_strtoupper($value));
            case 'in': return (bool) array_filter($list(), fn ($v) => $cmp($lhs, $v));
            case 'not_in': return ! array_filter($list(), fn ($v) => $cmp($lhs, $v));
            case 'gt': return $numeric && $lhs > (float) $value;
            case 'gte': return $numeric && $lhs >= (float) $value - 1e-9;
            case 'lt': return $numeric && $lhs < (float) $value;
            case 'lte': return $numeric && $lhs <= (float) $value + 1e-9;
            case 'between':
                [$a, $b] = array_pad($list(), 2, null);

                return $numeric && is_numeric($a) && is_numeric($b) && $lhs >= (float) $a - 1e-9 && $lhs <= (float) $b + 1e-9;
            case 'regex':
                return @preg_match('/'.str_replace('/', '\/', $value).'/i', (string) $raw) === 1;
        }

        return false;
    }

    /** Validates a rule definition; returns error strings (empty when fine). */
    public static function validateDefinition(string $kind, array $d): array
    {
        $errors = [];
        $checkGroup = function (mixed $g, string $where) use (&$checkGroup, &$errors) {
            if ($g === null || $g === []) {
                return;
            }
            if (! is_array($g) || ! in_array($g['logic'] ?? 'all', ['all', 'any'], true) || ! is_array($g['items'] ?? [])) {
                $errors[] = "{$where}: malformed condition group.";

                return;
            }
            foreach ($g['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    $errors[] = "{$where}: malformed condition.";
                } elseif (isset($item['items'])) {
                    $checkGroup($item, $where);
                } else {
                    if (! isset(self::FIELDS[$item['field'] ?? ''])) {
                        $errors[] = "{$where}: unknown field.";
                    }
                    if (! isset(self::OPS[$item['op'] ?? ''])) {
                        $errors[] = "{$where}: unknown operator.";
                    }
                    if (($item['op'] ?? '') === 'regex' && @preg_match('/'.str_replace('/', '\/', (string) ($item['value'] ?? '')).'/i', '') === false) {
                        $errors[] = "{$where}: invalid regular expression.";
                    }
                }
            }
        };

        foreach (['filter', 'require', 'when', 'partner'] as $g) {
            $checkGroup($d[$g] ?? null, $g);
        }
        if ($kind === 'row' && empty($d['require']['items'] ?? [])) {
            $errors[] = 'A row rule needs at least one "must be true" condition.';
        }
        if ($kind === 'companion') {
            if (empty($d['partner']['items'] ?? [])) {
                $errors[] = 'A companion rule needs at least one partner condition.';
            }
            if (! in_array($d['mode'] ?? 'require', ['require', 'forbid'], true)) {
                $errors[] = 'Mode must be require or forbid.';
            }
        }
        if ($kind === 'count') {
            if (! isset(self::METRICS[$d['metric'] ?? ''])) {
                $errors[] = 'Unknown metric.';
            }
            if (in_array($d['metric'] ?? '', ['sum', 'min', 'max', 'distinct'], true) && ! isset(self::FIELDS[$d['field'] ?? ''])) {
                $errors[] = 'This metric needs a field.';
            }
            if (! in_array($d['op'] ?? '', self::COMPARE_OPS, true)) {
                $errors[] = 'Unknown comparison.';
            }
            if (! is_numeric($d['value'] ?? null)) {
                $errors[] = 'Expected value must be a number.';
            }
        }

        return array_values(array_unique($errors));
    }

    // ---------------------------------------------------------------- output

    private function label(string $key): string
    {
        return trim(str_replace('|', ' / ', $key), ' /');
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function message(CutlistRule $rule, array $row, string $fallback, array $extra = []): string
    {
        $tpl = trim((string) $rule->message);
        if ($tpl === '') {
            $detail = $row ? trim(($row['part_number'] ?? '').' '.($row['use'] ?? '').($row['bay'] !== null ? ' (bay '.$this->num($row['bay']).')' : '').($row['length'] !== null ? ' @ '.$this->num($row['length']).'"' : '')) : '';

            return $rule->name.': '.$fallback.($detail !== '' ? ' — '.$detail : '').'.';
        }
        $vars = $extra;
        foreach (self::FIELDS as $f => $_) {
            $v = $row[$f] ?? '';
            $vars[$f] = is_float($v) ? $this->num($v) : (string) $v;
        }
        $vars['rule'] = $rule->name;

        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => $vars[$m[1]] ?? $m[0], $tpl);
    }
}
