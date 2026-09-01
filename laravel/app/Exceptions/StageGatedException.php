<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a stage / job-step transition is refused because an earlier
 * blocking step is not yet complete. Renders itself as a 422 the frontend can
 * recognise by `code = "stage_gated"`.
 */
class StageGatedException extends RuntimeException
{
    public function __construct(
        public readonly int $blockingId,
        public readonly string $blockingName,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : "Complete \"{$blockingName}\" first.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message'        => $this->getMessage(),
            'code'           => 'stage_gated',
            'blocking_stage' => [
                'id'   => $this->blockingId,
                'name' => $this->blockingName,
            ],
        ], 422);
    }
}
