<?php

namespace App\Services\Configurator;

use InvalidArgumentException;

/**
 * Structured hardware-variable formulas (formula_def), modelled on the frame
 * profile cut-length formula (a signed list of terms) plus optional branches.
 *
 * formula_def = { branches: [ { when: null | Condition, terms: [Term, ...] } ] }
 *   Term      = { sign: 1|-1, kind: 'var', var: CODE, mult: number }
 *             | { sign: 1|-1, kind: 'fixed', value: number }
 *   Condition = { type: 'var_true', var: CODE }       the variable resolves to "true"
 *             | { type: 'category', match: string }   another item on the opening is in a
 *                                                     category whose name contains `match`
 * Branches are tried in order; the first whose condition holds wins. A branch with
 * `when: null` always matches (use it last as the "otherwise").
 *
 * This class also converts the legacy text formulas ("A + B", "Label: expr · Label: expr")
 * into that structure, and renders a structure back to text for display.
 */
class HwlibFormulaConverter
{
    /** Legacy conditional formulas were selected by code-specific logic; this maps it to data. */
    private const LEGACY_CONDITIONS = [
        'STRIKEMNT_ADD' => [
            '/frame/i' => ['type' => 'var_true', 'var' => 'STRIKEMNT_FRAME'],
            '/stop/i' => ['type' => 'var_true', 'var' => 'STRIKEMNT_STOP'],
            '/mortise/i' => ['type' => 'var_true', 'var' => 'STRIKEMNT_MORTISE'],
        ],
        'EPT_CL_INSET_DR' => [
            '/continuous/i' => ['type' => 'category', 'match' => 'continuous hinge'],
            '/butt/i' => ['type' => 'category', 'match' => 'butt hinge'],
        ],
        'PANIC_BACKSET' => [
            '/mullion/i' => ['type' => 'category', 'match' => 'removable mullion'],
            '/standard/i' => null,
        ],
    ];

    /**
     * @return array|null formula_def, or null when the text can't be represented
     */
    public static function fromLegacy(string $code, ?string $formula): ?array
    {
        $formula = trim((string) $formula);
        if ($formula === '') {
            return null;
        }

        try {
            if (! str_contains($formula, '·')) {
                return ['branches' => [['when' => null, 'terms' => self::parseExpr($formula)]]];
            }

            $map = self::LEGACY_CONDITIONS[$code] ?? null;
            if (! $map) {
                return null;
            }
            $branches = [];
            $rank = array_flip(array_keys($map));
            foreach (explode('·', $formula) as $part) {
                if (! preg_match('/^\s*(.*?):\s*(.*)$/', $part, $m)) {
                    return null;
                }
                $when = null;
                $order = null;
                foreach ($map as $pattern => $cond) {
                    if (preg_match($pattern, $m[1])) {
                        $when = $cond;
                        $order = $rank[$pattern];
                        break;
                    }
                }
                if ($order === null) {
                    return null;
                }
                $branches[$order] = ['when' => $when, 'terms' => self::parseExpr($m[2])];
            }
            // Same precedence the old per-code logic used (e.g. continuous hinge before butt hinge).
            ksort($branches);
            $branches = array_values($branches);

            return ['branches' => $branches];
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** "A + B - (C + D) + E / 2 + 3/16" -> flat signed terms. */
    private static function parseExpr(string $expr): array
    {
        $expr = preg_replace('/\bif present\b/i', '', $expr);
        preg_match_all('/[A-Z][A-Z0-9_]*|\d+\.\d+|\d+\/\d+|\d+|[()+\-*\/]|\S/', $expr, $m);
        $t = $m[0];
        $pos = 0;
        $terms = self::parseSum($t, $pos, 1);
        if ($pos < count($t) || ! $terms) {
            throw new InvalidArgumentException('unsupported expression');
        }

        return $terms;
    }

    private static function parseSum(array $t, int &$pos, int $outer): array
    {
        $terms = [];
        $sign = 1;
        $expectOperand = true;
        while ($pos < count($t)) {
            $tok = $t[$pos];
            if ($tok === ')') {
                break;
            }
            if ($tok === '+' || $tok === '-') {
                if ($tok === '-') {
                    $sign = -$sign;
                }
                $pos++;
                $expectOperand = true;

                continue;
            }
            if (! $expectOperand) {
                throw new InvalidArgumentException('missing operator');
            }
            $pos++;
            if ($tok === '(') {
                $terms = array_merge($terms, self::parseSum($t, $pos, $outer * $sign));
                if (($t[$pos] ?? null) !== ')') {
                    throw new InvalidArgumentException('unbalanced');
                }
                $pos++;
            } elseif (preg_match('/^[A-Z]/', $tok)) {
                $terms[] = ['sign' => $outer * $sign, 'kind' => 'var', 'var' => $tok, 'mult' => self::parseMult($t, $pos)];
            } elseif (preg_match('/^[\d.\/]+$/', $tok)) {
                $value = self::num($tok);
                $mult = self::parseMult($t, $pos);
                $terms[] = ['sign' => $outer * $sign, 'kind' => 'fixed', 'value' => $value * $mult];
            } else {
                throw new InvalidArgumentException('unsupported token');
            }
            $sign = 1;
            $expectOperand = false;
        }

        return $terms;
    }

    /** Optional "* n" / "/ n" directly after an operand. */
    private static function parseMult(array $t, int &$pos): float
    {
        $mult = 1.0;
        while (in_array($t[$pos] ?? null, ['*', '/'], true)) {
            $op = $t[$pos];
            $n = $t[$pos + 1] ?? null;
            if ($n === null || ! preg_match('/^[\d.\/]+$/', $n)) {
                throw new InvalidArgumentException('multiplier must be a number');
            }
            $v = self::num($n);
            if ($op === '/' && $v == 0) {
                throw new InvalidArgumentException('divide by zero');
            }
            $mult = $op === '*' ? $mult * $v : $mult / $v;
            $pos += 2;
        }

        return $mult;
    }

    private static function num(string $s): float
    {
        if (str_contains($s, '/')) {
            [$n, $d] = array_map('floatval', explode('/', $s));
            if ($d == 0) {
                throw new InvalidArgumentException('divide by zero');
            }

            return $n / $d;
        }

        return (float) $s;
    }

    /** Human-readable text (kept in the legacy `formula` column for lists/exports). */
    public static function toText(array $def): string
    {
        $parts = [];
        foreach ($def['branches'] ?? [] as $b) {
            $text = '';
            foreach ($b['terms'] ?? [] as $i => $term) {
                $body = ($term['kind'] ?? '') === 'fixed'
                    ? rtrim(rtrim(number_format((float) ($term['value'] ?? 0), 4, '.', ''), '0'), '.')
                    : ($term['var'] ?? '?').(((float) ($term['mult'] ?? 1)) != 1.0 ? ' × '.rtrim(rtrim(number_format((float) $term['mult'], 4, '.', ''), '0'), '.') : '');
                $neg = ($term['sign'] ?? 1) < 0;
                $text .= $i === 0 ? ($neg ? '-' : '').$body : ($neg ? ' - ' : ' + ').$body;
            }
            $w = $b['when'] ?? null;
            $label = $w === null
                ? (count($def['branches']) > 1 ? 'Otherwise' : '')
                : ($w['type'] === 'var_true' ? 'If '.$w['var'] : 'With '.$w['match']);
            $parts[] = ($label !== '' ? $label.': ' : '').($text === '' ? '0' : $text);
        }

        return implode(' · ', $parts);
    }

    /** Every variable code a definition reads (terms and conditions). */
    public static function referencedCodes(array $def): array
    {
        $codes = [];
        foreach ($def['branches'] ?? [] as $b) {
            if (($b['when']['type'] ?? null) === 'var_true') {
                $codes[] = $b['when']['var'];
            }
            foreach ($b['terms'] ?? [] as $t) {
                if (($t['kind'] ?? '') === 'var') {
                    $codes[] = $t['var'];
                }
            }
        }

        return array_values(array_unique($codes));
    }
}
