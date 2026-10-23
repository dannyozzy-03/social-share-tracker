<?php

declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
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
}
