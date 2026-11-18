<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The application is installed or configured incorrectly (unreadable config file, missing template, …).
 *
 * Error handling: thrown by Config, View and SqlFile. Nothing catches it, so the handler in
 * src/bootstrap.php logs it and answers 500 (the installer prints it and exits).
 */
final class ConfigurationException extends AppException
{
}
