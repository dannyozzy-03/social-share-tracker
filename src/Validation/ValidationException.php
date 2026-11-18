<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exception\AppException;

/**
 * Input failed validation; errors() maps each field name to a message for the user.
 *
 * Error handling: thrown by PageSearch, PlatformForm, StatsFilter and ShareTracker. Entry points
 * catch it and answer 422 with errors() (the APIs), or show the errors on the form.
 */
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
