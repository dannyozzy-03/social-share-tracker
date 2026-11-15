<?php

declare(strict_types=1);

namespace App\Exception;

/** The application is installed or configured incorrectly (unreadable config file, missing template, …). */
final class ConfigurationException extends AppException
{
}
