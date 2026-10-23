<?php

declare(strict_types=1);

namespace App\Share;

use RuntimeException;

final class RateLimitException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Too many share requests. Please slow down.');
    }
}
