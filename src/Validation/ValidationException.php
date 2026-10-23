<?php

declare(strict_types=1);

namespace App\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
