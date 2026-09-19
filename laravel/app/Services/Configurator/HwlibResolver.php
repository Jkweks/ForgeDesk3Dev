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
 *   3. the variable's formula, if calculated (may branch — see
 *      selectBranch() for the handful of conditional formulas that need it)
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

    public function __construct(private DoorFrameConfiguration $config)
    {
        $this->build();
    }

    private function build(): void
    {
        $this->variablesByCode = ConfiguratorHwlibVariable::all()->keyBy('code')->all();

        $links = $this->config->hardwareLinks()->with(['item.category.variables', 'item.values', 'values'])->get();
        $angle = $this->config->doorConfigs->first()->opening_angle ?? 90;

        $linkIds = $links->pluck('id')->all();

        foreach ($links as $link) {
            $this->angleByLink[$link->id] = $angle;
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
            } elseif ($raw->default_value !== null && $raw->default_value !== '') {
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
            if ($otherRaw->default_value !== null && $otherRaw->default_value !== '') {
                return ['value' => $otherRaw->default_value, 'overridden' => false];
            }
        }

        if ($code === 'LOCK_STOP_Z' && $this->lockStopZ !== null) {
            return ['value' => (string) $this->lockStopZ, 'overridden' => false];
        }

        $globalVar = $this->variablesByCode[$code] ?? null;
        if ($globalVar && $globalVar->default_value !== null && $globalVar->default_value !== '') {
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

        $resolveNum = function (string $code) use ($linkId, &$seen, &$anyOverridden) {
            $r = $this->resolveVar($code, $linkId, $seen);
            if ($r['overridden']) {
                $anyOverridden = true;
            }

            return $this->toNum($r['value']);
        };
        $resolveRaw = function (string $code) use ($linkId, &$seen) {
            return $this->resolveVar($code, $linkId, $seen)['value'];
        };

        if (str_contains($formula, '·')) {
            $branch = $this->selectBranch($raw->code, $this->parseBranches($formula), $resolveRaw, $this->sameComboCategories[$linkId] ?? []);
            if (! $branch) {
                return ['value' => null, 'overridden' => false];
            }
            $result = $this->evalExpr($branch['expr'], $resolveNum);
        } else {
            $result = $this->evalExpr($formula, $resolveNum);
        }

        if ($result === null) {
            return ['value' => null, 'overridden' => false];
        }

        return ['value' => (string) (round($result * 10000) / 10000), 'overridden' => $anyOverridden];
    }

    /**
     * "Label1: expr1 · Label2: expr2 · ..." -> [{label, expr}, ...]
     */
    private function parseBranches(string $formula): array
    {
        return array_map(function ($part) {
            $part = trim($part);
            if (preg_match('/^(.*?):\s*(.*)$/', $part, $m)) {
                return ['label' => trim($m[1]), 'expr' => trim($m[2])];
            }

            return ['label' => '', 'expr' => $part];
        }, explode('·', $formula));
    }

    /**
     * Picks which branch of a conditional formula applies, per variable code.
     * Only the conditional formulas that actually exist in the catalog are
     * handled — an unrecognized one resolves to null rather than guessing.
     */
    private function selectBranch(string $code, array $branches, callable $resolveRaw, array $sameComboCategories): ?array
    {
        $find = fn (string $pattern) => collect($branches)->first(fn ($b) => preg_match($pattern, $b['label']));

        if ($code === 'STRIKEMNT_ADD') {
            if ($resolveRaw('STRIKEMNT_FRAME') === 'true') {
                return $find('/frame/i');
            }
            if ($resolveRaw('STRIKEMNT_STOP') === 'true') {
                return $find('/stop/i');
            }
            if ($resolveRaw('STRIKEMNT_MORTISE') === 'true') {
                return $find('/mortise/i');
            }

            return null;
        }

        if ($code === 'EPT_CL_INSET_DR') {
            if (collect($sameComboCategories)->contains(fn ($c) => preg_match('/continuous hinge/i', $c))) {
                return $find('/continuous/i');
            }
            if (collect($sameComboCategories)->contains(fn ($c) => preg_match('/butt hinge/i', $c))) {
                return $find('/butt/i');
            }

            return null;
        }

        if ($code === 'PANIC_BACKSET') {
            if (collect($sameComboCategories)->contains(fn ($c) => preg_match('/removable mullion/i', $c))) {
                return $find('/mullion/i');
            }

            return $find('/standard/i') ?? end($branches) ?: null;
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
