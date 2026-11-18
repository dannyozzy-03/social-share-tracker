<?php

declare(strict_types=1);

namespace App\Security;

use App\Exception\AppException;
use Exception;

/**
 * One CSRF token per admin session, checked on every admin form.
 *
 * Error handling: the only I/O is random_bytes(). It catches the failure when the system has no
 * secure random source and throws AppException instead, rather than issuing a guessable token.
 */
final class Csrf
{
    private const KEY = '_csrf';

    /** @throws AppException when no secure random source is available */
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            try {
                $_SESSION[self::KEY] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // random_bytes() throws only when the OS has no secure random source.
                throw new AppException('Could not create a security token.', 0, $e);
            }
        }

        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION[self::KEY])
            && is_string($_SESSION[self::KEY])
            && hash_equals($_SESSION[self::KEY], $token);
    }

    /** Discards the current token; the next token() call issues a new one. */
    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
