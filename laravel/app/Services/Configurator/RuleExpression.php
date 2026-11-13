<?php

namespace App\Services\Configurator;

use RuntimeException;

/**
 * Arithmetic expressions typed into configurator rules, e.g.
 *
 *   PANIC_CL_AFF - THRESHOLD - P2451.length / 2
 *   if(HAS_THRESHOLD, 2, 1)
 *   max(ORIGINAL - 7, 0)
 *
 * Supports + - * / and parentheses, comparisons (> < >= <= == !=, giving 1 or 0), numbers
 * (decimals; a fraction is just a division: 1/2), names (letters, digits, underscores, with an
 * optional ".property"), quoted strings as function arguments, and the functions min, max, abs,
 * round(x [, step]) and if(condition, then, else). Any other function, and every name, is resolved
 * by the caller.
 *
 * A name or function the caller cannot resolve (returns null) fails the whole expression with a
 * RuntimeException naming it — a rule must never cut a part from a guessed number.
 */
class RuleExpression
{
    private const TOKEN = '/\G\s*(?:(\d+\.\d+|\.\d+|\d+)|([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)|\'([^\']*)\'|"([^"]*)"|(>=|<=|==|!=|[-+*\/(),<>]))/A';

    /** @var array<int, array{0: string, 1: mixed}> */
    private array $tokens = [];

    private int $pos = 0;

    /** @var callable(string): ?float */
    private $ident;

    /** @var callable(string, array): ?float */
    private $func;

    /**
     * @param  callable(string): ?float  $ident  value of a name, null when unknown
     * @param  (callable(string, array): ?float)|null  $func  value of a non-built-in function call
     */
    public static function evaluate(string $expr, callable $ident, ?callable $func = null): float
    {
        $self = new self;
        $self->tokens = self::tokenize($expr);
        $self->ident = $ident;
        $self->func = $func ?? fn () => null;

        if (! $self->tokens) {
            throw new RuntimeException('the expression is empty');
        }
        $value = $self->comparison();
        if ($self->pos < count($self->tokens)) {
            throw new RuntimeException('unexpected "'.$self->tokens[$self->pos][1].'"');
        }
        if (is_nan($value) || is_infinite($value)) {
            throw new RuntimeException('the result is not a number');
        }

        return $value;
    }

    /** Syntax check only: every name and function is taken as 1. Returns the problem, or null. */
    public static function syntaxError(string $expr): ?string
    {
        try {
            self::evaluate($expr, fn () => 1.0, fn () => 1.0);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** Names an expression reads (function names excluded). */
    public static function identifiers(string $expr): array
    {
        try {
            $tokens = self::tokenize($expr);
        } catch (RuntimeException) {
            return [];
        }
        $names = [];
        foreach ($tokens as $i => [$type, $value]) {
            if ($type === 'name' && ($tokens[$i + 1][1] ?? null) !== '(') {
                $names[] = $value;
            }
        }

        return array_values(array_unique($names));
    }

    private static function tokenize(string $expr): array
    {
        $tokens = [];
        $offset = 0;
        $expr = rtrim($expr);
        while ($offset < strlen($expr)) {
            if (! preg_match(self::TOKEN, $expr, $m, 0, $offset)) {
                throw new RuntimeException('cannot read "'.trim(substr($expr, $offset, 12)).'"');
            }
            $offset += strlen($m[0]);
            if (($m[1] ?? '') !== '') {
                $tokens[] = ['num', (float) $m[1]];
            } elseif (($m[2] ?? '') !== '') {
                $tokens[] = ['name', $m[2]];
            } elseif (isset($m[5]) && $m[5] !== '') {
                $tokens[] = ['op', $m[5]];
            } else {
                $tokens[] = ['str', ($m[3] ?? '') !== '' ? $m[3] : ($m[4] ?? '')];
            }
        }

        return $tokens;
    }

    private function peekOp(): ?string
    {
        $t = $this->tokens[$this->pos] ?? null;

        return $t && $t[0] === 'op' ? $t[1] : null;
    }

    private function expectOp(string $op): void
    {
        if ($this->peekOp() !== $op) {
            throw new RuntimeException("expected \"{$op}\"");
        }
        $this->pos++;
    }

    private function comparison(): float
    {
        $left = $this->sum();
        $op = $this->peekOp();
        if (! in_array($op, ['>', '<', '>=', '<=', '==', '!='], true)) {
            return $left;
        }
        $this->pos++;
        $right = $this->sum();

        return (float) match ($op) {
            '>' => $left > $right + 1e-9,
            '<' => $left < $right - 1e-9,
            '>=' => $left >= $right - 1e-9,
            '<=' => $left <= $right + 1e-9,
            '==' => abs($left - $right) < 1e-9,
            default => abs($left - $right) >= 1e-9,
        };
    }

    private function sum(): float
    {
        $value = $this->product();
        while (in_array($op = $this->peekOp(), ['+', '-'], true)) {
            $this->pos++;
            $rhs = $this->product();
            $value = $op === '+' ? $value + $rhs : $value - $rhs;
        }

        return $value;
    }

    private function product(): float
    {
        $value = $this->unary();
        while (in_array($op = $this->peekOp(), ['*', '/'], true)) {
            $this->pos++;
            $rhs = $this->unary();
            if ($op === '/' && abs($rhs) < 1e-12) {
                throw new RuntimeException('division by zero');
            }
            $value = $op === '*' ? $value * $rhs : $value / $rhs;
        }

        return $value;
    }

    private function unary(): float
    {
        if ($this->peekOp() === '-') {
            $this->pos++;

            return -$this->unary();
        }

        return $this->atom();
    }

    private function atom(): float
    {
        $token = $this->tokens[$this->pos] ?? null;
        if (! $token) {
            throw new RuntimeException('the expression ends too soon');
        }
        $this->pos++;
        [$type, $value] = $token;

        if ($type === 'num') {
            return $value;
        }
        if ($type === 'op' && $value === '(') {
            $inner = $this->comparison();
            $this->expectOp(')');

            return $inner;
        }
        if ($type === 'name') {
            if ($this->peekOp() === '(') {
                return $this->call($value);
            }
            $resolved = ($this->ident)($value);
            if ($resolved === null) {
                throw new RuntimeException("{$value} has no value");
            }

            return (float) $resolved;
        }

        throw new RuntimeException('unexpected "'.$value.'"');
    }

    private function call(string $name): float
    {
        $this->expectOp('(');
        $args = [];
        if ($this->peekOp() !== ')') {
            do {
                $next = $this->tokens[$this->pos] ?? null;
                if ($next && $next[0] === 'str') {
                    $this->pos++;
                    $args[] = $next[1];
                } else {
                    $args[] = $this->comparison();
                }
                $more = $this->peekOp() === ',';
                if ($more) {
                    $this->pos++;
                }
            } while ($more);
        }
        $this->expectOp(')');

        $numeric = fn () => array_map(function ($a) use ($name) {
            if (! is_float($a) && ! is_int($a)) {
                throw new RuntimeException("{$name}() takes numbers");
            }

            return (float) $a;
        }, $args);
        $need = function (int $min, ?int $max = null) use ($args, $name) {
            if (count($args) < $min || ($max !== null && count($args) > $max)) {
                throw new RuntimeException("{$name}() has the wrong number of values");
            }
        };

        switch (strtolower($name)) {
            case 'min':
                $need(1);

                return min($numeric());
            case 'max':
                $need(1);

                return max($numeric());
            case 'abs':
                $need(1, 1);

                return abs($numeric()[0]);
            case 'round':
                $need(1, 2);
                $n = $numeric();
                $step = $n[1] ?? 1.0;
                if ($step <= 0) {
                    throw new RuntimeException('round() needs a step above zero');
                }

                return round($n[0] / $step) * $step;
            case 'if':
                $need(3, 3);
                $n = $numeric();

                return abs($n[0]) > 1e-9 ? $n[1] : $n[2];
        }

        $resolved = ($this->func)(strtolower($name), $args);
        if ($resolved === null) {
            $shown = implode(', ', array_map(fn ($a) => is_string($a) ? "'{$a}'" : (string) $a, $args));
            throw new RuntimeException("{$name}({$shown}) has no value");
        }

        return (float) $resolved;
    }
}
