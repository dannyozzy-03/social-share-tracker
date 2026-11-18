<?php

declare(strict_types=1);

namespace App\Share;

use App\Exception\AppException;

/**
 * One visitor sent more share clicks than tracking.max_per_minute allows.
 *
 * Error handling: thrown by ShareTracker. api/track.php catches it and answers 429 with Retry-After.
 */
final class RateLimitException extends AppException
{
    public function __construct()
    {
        parent::__construct('Too many share requests. Please slow down.');
    }
}
