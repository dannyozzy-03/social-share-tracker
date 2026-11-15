<?php

declare(strict_types=1);

namespace App\Share;

use App\Exception\AppException;

final class RateLimitException extends AppException
{
    public function __construct()
    {
        parent::__construct('Too many share requests. Please slow down.');
    }
}
