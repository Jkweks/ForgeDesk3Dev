<?php

namespace App\Services\Pm;

use RuntimeException;

/** The worker understood the request but the file is not a readable estimate (a data problem, not an outage). */
class PmWorkerDataException extends RuntimeException {}
