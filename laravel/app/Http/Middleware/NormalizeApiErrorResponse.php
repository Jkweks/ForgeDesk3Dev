<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controllers across the API are inconsistent about whether an error response
 * carries a `message` key, an `error` key, or both — the frontend has to
 * defensively check `error.error ?? error.message` everywhere as a result.
 * Rather than rewriting every controller's response shape (a large, risky
 * change with no test coverage to catch regressions), this normalizes at the
 * edge: any JSON error response gets both keys mirrored so either one is
 * always present, without touching business logic in controllers.
 */
class NormalizeApiErrorResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $data = $response->getData(true);

            if (is_array($data)) {
                $hasMessage = array_key_exists('message', $data) && $data['message'] !== null;
                $hasError = array_key_exists('error', $data) && $data['error'] !== null;

                if ($hasError && !$hasMessage) {
                    $data['message'] = $data['error'];
                    $response->setData($data);
                } elseif ($hasMessage && !$hasError) {
                    $data['error'] = $data['message'];
                    $response->setData($data);
                }
            }
        }

        return $response;
    }
}
