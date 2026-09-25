<?php

namespace App\Support;

/**
 * Exact-IP / IPv4-CIDR matching against a configured allowlist. Mirrors the
 * same check tiger-bridge/server.js does for its ALLOWED_CLIENT_IPS — kept
 * here rather than pulled from a package since the logic is a handful of
 * lines and this avoids a dependency for something this small.
 */
class IpAllowlist
{
    public static function allows(?string $ip, array $rules): bool
    {
        if ($rules === []) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        foreach ($rules as $rule) {
            if (static::matches($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    protected static function matches(string $ip, string $rule): bool
    {
        if ($rule === $ip) {
            return true;
        }

        if (str_contains($rule, '/')) {
            [$rangeIp, $prefix] = explode('/', $rule, 2);

            $rangeLong = ip2long($rangeIp);
            $ipLong = ip2long($ip);
            $prefix = (int) $prefix;

            if ($rangeLong === false || $ipLong === false || $prefix < 0 || $prefix > 32) {
                return false;
            }

            $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix));

            return ($rangeLong & $mask) === ($ipLong & $mask);
        }

        return false;
    }
}
