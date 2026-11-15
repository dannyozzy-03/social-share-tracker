<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exception\AppException;

/** Input failed validation; errors() maps each field name to a message for the user. */
final class ValidationException extends AppException
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
