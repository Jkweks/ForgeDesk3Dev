<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorFrameProfile;
use App\Models\ConfiguratorHwlibVariable;
use App\Models\DoorFrameConfiguration;

/**
 * Resolves a hardware variable's *effective* value for a given hwlib link
 * (an item attached to an opening). Direct port of fab_utils'
 * hwlib-hardware-calculator.html resolution engine (hwlibResolveVar /
 * hwlibEvaluateCalculated / hwlibEvalExpr / hwlibSelectBranch) — see that
 * file if this ever needs re-verifying.
 *
 * Priority chain per variable code, for one link:
 *   1. the link's own override value (degree_matrix: cell at the opening's
 *      angle; otherwise the raw override text)
 *   2. the item's catalog value (same degree_matrix / raw split)
 *   3. the variable's formula, if calculated (formula_def: signed terms, optionally
 *      split into conditional branches — see HwlibFormulaConverter)
 *   4. the SAME variable code on another hwlib link attached to the same
 *      configuration (a formula can reference another item's value — e.g. a
 *      Strike's STRIKEMNT_ADD feeding a Panic's PANIC_BACKSET)
 *   5. LOCK_STOP_Z only: the paired frame series' "Lock Jamb Stop" profile's
 *      section_height (a real cross-reference into the frame catalog)
 *   6. the variable's global default_value
 */
class HwlibResolver
{
    /** @var array<string, ConfiguratorHwlibVariable> code => variable */
    private array $variablesByCode = [];

    /** @var array<int, array<int, object>> linkId => list of {code, var_type, is_calculated, formula, link_value_text, item_value_text} */
    private array $rawVarsByLink = [];

    /** @var array<int, int> linkId => opening angle */
    private array $angleByLink = [];

    /** @var array<int, array<int, int>> linkId => other linkIds on the same configuration */
    private array $sameComboLinkIds = [];

    /** @var array<int, array<int, string>> linkId => category names of other same-combo items */
    private array $sameComboCategories = [];

    private ?float $lockStopZ = null;

    /**
     * @param bool $enteredOnly Report mode: only values someone actually entered (link override or item
     *                          catalog value) count. Global/category defaults are ignored, and a calculated
     *                          value is dropped if any variable it uses has no entered value.
     */
    public function __construct(private DoorFrameConfiguration $config, private bool $enteredOnly = false)
    {
        $this->build();
    }

    private function build(): void
    {
        $this->variablesByCode = ConfiguratorHwlibVariable::all()->keyBy('code')->all();

        $links = $this->config->hardwareLinks()->with(['item.category.variables', 'item.values', 'values'])->get();

        $linkIds = $links->pluck('id')->all();

        foreach ($links as $link) {
            $this->angleByLink[$link->id] = $this->config->openingAngle($link->leaf);
            $this->sameComboLinkIds[$link->id] = array_values(array_diff($linkIds, [$link->id]));
            $this->sameComboCategories[$link->id] = $links->where('id', '!=', $link->id)
                ->map(fn ($l) => $l->item->category->name)->unique()->values()->all();

            $itemValues = $link->item->values->keyBy('variable_id');
            $linkValues = $link->values->keyBy('variable_id');

            $raw = [];
            foreach ($link->item->category->variables as $variable) {
                $raw[] = (object) [
                    'code' => $variable->code,
                    'var_type' => $variable->var_type,
                    'is_calculated' => $variable->is_calculated,
                    'formula' => $variable->formula,
                    'formula_def' => $variable->formula_def,
                    'default_value' => $variable->default_value,
                    'link_value_text' => optional($linkValues->get($variable->id))->value_text,
                    'item_value_text' => optional($itemValues->get($variable->id))->value_text,
                ];
            }
            $this->rawVarsByLink[$link->id] = $raw;
        }

        // LOCK_STOP_Z: the paired frame series' "Lock Jamb Stop" profile's section_height.
        $frameSeriesId = $this->config->frameConfig->frame_series_id ?? null;
        if ($frameSeriesId) {
            $this->lockStopZ = ConfiguratorFrameProfile::where('frame_series_id', $frameSeriesId)
                ->where('role_label', 'Lock Jamb Stop')
                ->value('section_height');
            $this->lockStopZ = $this->lockStopZ !== null ? (float) $this->lockStopZ : null;
        }
    }

    /**
     * Resolve every report-worthy variable for every hwlib link on this
     * configuration.
     *
     * @return array<int, array<string, array{value: ?string, overridden: bool}>> linkId => code => result
     */
    public function resolveAll(): array
    {
        $out = [];
        foreach ($this->rawVarsByLink as $linkId => $vars) {
            foreach ($vars as $var) {
                $out[$linkId][$var->code] = $this->resolveVar($var->code, $linkId);
            }
        }

        return $out;
    }

    /**
     * Whether a variable has anything actually entered for this link — an override on the link, a
     * value on the item (its hardware standard) — or is calculated (which never carries its own
     * value by design, so counts as configured whenever it resolves). Used to hide an item's whole
     * variable block on reports when nothing about it was ever configured.
     */
    public function isConfigured(int $linkId, string $code): bool
    {
        $raw = collect($this->rawVarsByLink[$linkId] ?? [])->firstWhere('code', $code);
        if (! $raw) {
            return false;
        }

        return (bool) $raw->is_calculated
            || ($raw->item_value_text !== null && $raw->item_value_text !== '')
            || ($raw->link_value_text !== null && $raw->link_value_text !== '');
    }

    /**
     * @return array{value: ?string, overridden: bool}
     */
    public function resolveVar(string $code, int $linkId, array &$seen = []): array
    {
        $seenKey = "{$linkId}:{$code}";
        if (isset($seen[$seenKey])) {
            return ['value' => null, 'overridden' => false];
        }
        $seen[$seenKey] = true;

        $raw = collect($this->rawVarsByLink[$linkId] ?? [])->firstWhere('code', $code);

        if ($raw) {
            if ($raw->var_type === 'degree_matrix') {
                $matrix = json_decode($raw->link_value_text ?? $raw->item_value_text ?? '{}', true) ?: [];
                $angle = $this->angleByLink[$linkId] ?? 90;
                $val = $matrix[(string) $angle] ?? null;
                if ($val !== null && $val !== '') {
                    return ['value' => $val, 'overridden' => $raw->link_value_text !== null];
                }
            } elseif ($raw->link_value_text !== null && $raw->link_value_text !== '') {
                return ['value' => $raw->link_value_text, 'overridden' => true];
            } elseif ($raw->item_value_text !== null && $raw->item_value_text !== '') {
                return ['value' => $raw->item_value_text, 'overridden' => false];
            } elseif ($raw->is_calculated) {
                $computed = $this->evaluateCalculated($raw, $linkId, $seen);
                if ($computed['value'] !== null) {
                    return $computed;
                }
            } elseif (! $this->enteredOnly && $raw->default_value !== null && $raw->default_value !== '') {
                return ['value' => $raw->default_value, 'overridden' => false];
            }
        }

        // Other hardware linked to the same opening.
        foreach ($this->sameComboLinkIds[$linkId] ?? [] as $otherId) {
            $otherRaw = collect($this->rawVarsByLink[$otherId] ?? [])->firstWhere('code', $code);
            if (! $otherRaw) {
                continue;
            }
            if ($otherRaw->link_value_text !== null && $otherRaw->link_value_text !== '') {
                return ['value' => $otherRaw->link_value_text, 'overridden' => true];
            }
            if ($otherRaw->item_value_text !== null && $otherRaw->item_value_text !== '') {
                return ['value' => $otherRaw->item_value_text, 'overridden' => false];
            }
            if ($otherRaw->is_calculated) {
                $computed = $this->evaluateCalculated($otherRaw, $otherId, $seen);
                if ($computed['value'] !== null) {
                    return $computed;
                }
            }
            if (! $this->enteredOnly && $otherRaw->default_value !== null && $otherRaw->default_value !== '') {
                return ['value' => $otherRaw->default_value, 'overridden' => false];
            }
        }

        if ($code === 'LOCK_STOP_Z' && $this->lockStopZ !== null) {
            return ['value' => (string) $this->lockStopZ, 'overridden' => false];
        }

        $globalVar = $this->variablesByCode[$code] ?? null;
        if (! $this->enteredOnly && $globalVar && $globalVar->default_value !== null && $globalVar->default_value !== '') {
            return ['value' => $globalVar->default_value, 'overridden' => false];
        }

        return ['value' => null, 'overridden' => false];
    }

    /**
     * @return array{value: ?string, overridden: bool}
     */
    private function evaluateCalculated(object $raw, int $linkId, array &$seen): array
    {
        $formula = $raw->formula ?? '';
        $anyOverridden = false;

        $missing = false;
        $resolveNum = function (string $code) use ($linkId, &$seen, &$anyOverridden, &$missing) {
            $r = $this->resolveVar($code, $linkId, $seen);
            if ($r['overridden']) {
                $anyOverridden = true;
            }
            $num = $this->toNum($r['value']);
            if ($num === null) {
                $missing = true;
            }

            return $num;
        };
        $resolveRaw = function (string $code) use ($linkId, &$seen) {
            return $this->resolveVar($code, $linkId, $seen)['value'];
        };

        $def = $raw->formula_def ?? null;
        if (is_array($def)) {
            $branch = $this->selectBranch($def['branches'] ?? [], $resolveRaw, $this->sameComboCategories[$linkId] ?? []);
            if (! $branch) {
                return ['value' => null, 'overridden' => false];
            }
            $result = 0.0;
            foreach ($branch['terms'] ?? [] as $term) {
                $sign = ($term['sign'] ?? 1) < 0 ? -1 : 1;
                if (($term['kind'] ?? '') === 'fixed') {
                    $result += $sign * (float) ($term['value'] ?? 0);
                } else {
                    // An unresolvable variable counts as 0 (and flags "missing" for reports).
                    $result += $sign * (float) ($term['mult'] ?? 1) * ($resolveNum((string) ($term['var'] ?? '')) ?? 0);
                }
            }
        } elseif ($formula !== '' && ! str_contains($formula, '·')) {
            // Legacy plain-text formula that couldn't be converted to formula_def.
            $result = $this->evalExpr($formula, $resolveNum);
        } else {
            return ['value' => null, 'overridden' => false];
        }

        if ($result === null || ($this->enteredOnly && $missing)) {
            return ['value' => null, 'overridden' => false];
        }

        return ['value' => (string) (round($result * 10000) / 10000), 'overridden' => $anyOverridden];
    }

    /**
     * First branch whose condition holds (a null condition always holds).
     */
    private function selectBranch(array $branches, callable $resolveRaw, array $sameComboCategories): ?array
    {
        foreach ($branches as $branch) {
            $when = $branch['when'] ?? null;
            if ($when === null) {
                return $branch;
            }
            if (($when['type'] ?? '') === 'var_true' && $resolveRaw((string) ($when['var'] ?? '')) === 'true') {
                return $branch;
            }
            if (($when['type'] ?? '') === 'category'
                && collect($sameComboCategories)->contains(fn ($c) => stripos($c, (string) ($when['match'] ?? '')) !== false)) {
                return $branch;
            }
        }

        return null;
    }

    private function toNum(?string $str): ?float
    {
        if ($str === null) {
            return null;
        }
        $s = trim($str);
        if (preg_match('#^-?\d+/\d+$#', $s)) {
            [$n, $d] = array_map('floatval', explode('/', $s));

            return $d != 0 ? $n / $d : null;
        }
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    /**
     * Recursive-descent evaluator for "CODE (+|-|*|/) CODE|number (...)"
     * style expressions. resolveNum(code) supplies a variable's numeric
     * value; a code it can't resolve is treated as 0, not as a failure.
     */
    private function evalExpr(string $expr, callable $resolveNum): ?float
    {
        $expr = preg_replace('/\bif present\b/i', '', $expr);
        preg_match_all('/[A-Z][A-Z0-9_]*|\d+\.\d+|\d+\/\d+|\d+|[()+\-*\/]/', $expr, $m);
        $tokens = $m[0];
        if (empty($tokens)) {
            return null;
        }

        $pos = 0;
        $peek = function () use (&$pos, $tokens) {
            return $tokens[$pos] ?? null;
        };
        $next = function () use (&$pos, $tokens) {
            return $tokens[$pos++] ?? null;
        };

        $parseAtom = function () use (&$parseAtom, &$parseExpr, $peek, $next, $resolveNum) {
            $t = $next();
            if ($t === '(') {
                $v = $parseExpr();
                if ($peek() === ')') {
                    $next();
                }

                return $v;
            }
            if (preg_match('/^[A-Z]/', $t)) {
                $v = $resolveNum($t);

                return $v ?? 0;
            }
            if (str_contains($t, '/')) {
                [$n, $d] = array_map('floatval', explode('/', $t));

                return $d != 0 ? $n / $d : 0;
            }

            return (float) $t;
        };

        $parseTerm = function () use (&$parseAtom, $peek, $next) {
            $v = $parseAtom();
            while (in_array($peek(), ['*', '/'], true)) {
                $op = $next();
                $rhs = $parseAtom();
                $v = $op === '*' ? $v * $rhs : ($rhs != 0 ? $v / $rhs : 0);
            }

            return $v;
        };

        $parseExpr = function () use (&$parseTerm, $peek, $next) {
            $v = $parseTerm();
            while (in_array($peek(), ['+', '-'], true)) {
                $op = $next();
                $rhs = $parseTerm();
                $v = $op === '+' ? $v + $rhs : $v - $rhs;
            }

            return $v;
        };

        $result = $parseExpr();

        return is_nan($result) ? null : $result;
    }
}
