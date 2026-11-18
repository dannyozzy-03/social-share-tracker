<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Base class for every exception the application throws on purpose.
 *
 * Error-handling convention (see docs/DETAILS.md, "Error handling"):
 * - Classes that touch I/O (database, session, files, randomness) catch the low-level
 *   error (PDOException, ErrorException, …) and rethrow an AppException subclass with
 *   a safe message and the original as $previous.
 * - Pure classes (validation, URL helpers, value objects) have nothing to catch and only
 *   throw ValidationException for bad input.
 * - Entry points in public/ catch the exceptions they can answer (422, 429, …); anything
 *   else reaches the handler in src/bootstrap.php, which logs it and answers 500/503.
 * - Every class in src/ states which of these it is in an "Error handling:" line like the one
 *   below, and tests/ErrorHandlingConventionTest.php checks that the line matches the code.
 *
 * Error handling: thrown by Auth and Csrf when a session or the secure random source fails.
 * Nothing catches it, so the handler in src/bootstrap.php logs it and answers 500.
 */
class AppException extends RuntimeException
{
}
