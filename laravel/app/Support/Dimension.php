<?php

namespace App\Support;

class Dimension
{
    /**
     * Parse a shop-style dimension string like "96", "48.375" or "96 3/8"
     * (as typed on the keypad) into a decimal number of inches.
     */
    public static function parse(string $raw): float
    {
        $whole = 0.0;
        $frac = 0.0;

        foreach (preg_split('/\s+/', trim($raw)) as $token) {
            if ($token === '') {
                continue;
            }

            if (str_contains($token, '/')) {
                [$num, $den] = array_pad(explode('/', $token, 2), 2, '1');
                $den = (float) $den;
                if ($den > 0) {
                    $frac = (float) $num / $den;
                }
            } else {
                $whole += (float) $token;
            }
        }

        return $whole + $frac;
    }

    public static function format(float $inches): string
    {
        return number_format($inches, 3, '.', '');
    }

    /**
     * Render a decimal inch value the way the keypad accepts it back, e.g.
     * 45.125 -> "45 1/8". Rounds to the nearest 1/32". Used for the
     * "Elevation - Length" line on the printed label.
     */
    public static function toFraction(float $inches, int $denominator = 32): string
    {
        $whole = (int) floor($inches);
        $fracPart = $inches - $whole;

        $numerator = (int) round($fracPart * $denominator);

        if ($numerator === $denominator) {
            $whole++;
            $numerator = 0;
        }

        if ($numerator === 0) {
            return (string) $whole;
        }

        $gcd = self::gcd($numerator, $denominator);
        $numerator /= $gcd;
        $reducedDenominator = $denominator / $gcd;

        return $whole > 0
            ? "{$whole} {$numerator}/{$reducedDenominator}"
            : "{$numerator}/{$reducedDenominator}";
    }

    protected static function gcd(int $a, int $b): int
    {
        return $b === 0 ? max($a, 1) : self::gcd($b, $a % $b);
    }
}
