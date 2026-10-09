<?php

namespace App\Services;

use App\Models\FdUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived proof that a shop-floor kiosk user entered their PIN. The kiosk
 * API is otherwise unauthenticated and fab user ids are listed publicly, so
 * privileged actions (gate overrides) require this token as well as the id.
 * Only a hash of the token is cached, keyed per user.
 */
class KioskToken
{
    private const TTL_HOURS = 12;

    public static function issue(FdUser $user): string
    {
        $token = Str::random(48);
        Cache::put(self::key($user->id, $token), true, now()->addHours(self::TTL_HOURS));

        return $token;
    }

    public static function verify(?string $token, FdUser $user): bool
    {
        return is_string($token) && $token !== '' && Cache::has(self::key($user->id, $token));
    }

    private static function key(int $userId, string $token): string
    {
        return 'kiosk-token:'.$userId.':'.hash('sha256', $token);
    }
}
