<?php

declare(strict_types=1);

/*
 * Guards the error-handling convention for every class in src/ (docs/DETAILS.md, "Error handling"),
 * so a class added or changed later cannot quietly break it:
 *
 *   1. Every exception class extends App\Exception\AppException.
 *   2. Every class that runs SQL catches PDOException, and every one of its public methods turns
 *      a driver error into a DatabaseException (checked by calling them against a failing PDO).
 *   3. The classes without any try/catch are exactly the pure ones, and they do no I/O.
 *   4. Every class says which of these it is in an "Error handling:" line in its class comment,
 *      and that line matches the code: "nothing to catch" exactly when it has no try/catch.
 */

use App\Exception\AppException;
use App\Exception\DatabaseException;
use App\Stats\StatsFilter;
use Tests\Support\FailingPdo;

require_once __DIR__ . '/Support/FailingPdo.php';

/** @return array<string, string> file path => class name, for every class file in src/ */
$classFiles = static function (): array {
    $root = str_replace('\\', '/', APP_ROOT) . '/src/';
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/src', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if ($file->getExtension() !== 'php' || in_array(basename($path), ['bootstrap.php', 'helpers.php'], true)) {
            continue; // bootstrap.php holds the global handler; helpers.php holds functions, not a class
        }
        $files[$path] = 'App\\' . str_replace('/', '\\', substr($path, strlen($root), -4));
    }
    ksort($files);

    return $files;
};

/** Classes with nothing to catch: no I/O, they only compute or validate. Documented in docs/DETAILS.md. */
$pureClasses = [
    'App\Admin\PageSearch', 'App\Admin\PlatformForm', 'App\Content\ArticleRepository',
    'App\Share\ShareLinkBuilder', 'App\Share\ShareTracker', 'App\Stats\StatsFilter',
    'App\Support\Paginator', 'App\Support\Url', 'App\Validation\Validator',
    // Exception classes are thrown, not catchers.
    'App\Exception\AppException', 'App\Exception\ConfigurationException', 'App\Exception\DatabaseException',
    'App\Share\RateLimitException', 'App\Validation\ValidationException',
];

/** The file's PHP code with comments removed, so prose like "new PDO()" in a docblock is not mistaken for code. */
$codeOf = static function (string $path): string {
    $code = '';
    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $code .= is_array($token) ? $token[1] : $token;
        }
    }

    return $code;
};

$runsSql = static fn (string $code): bool => preg_match('/->(prepare|query|exec)\(|new PDO\(/', $code) === 1;

/** Dummy arguments for calling a method: by parameter name first, then by type. */
$argumentsFor = static function (ReflectionMethod $method): array {
    $byName = [
        'data' => ['slug' => 'reddit', 'name' => 'Reddit'],
        'urls' => ['https://example.com/'],
        'platforms' => [['id' => 1, 'slug' => 'facebook', 'name' => 'Facebook', 'color' => '#1877F2', 'icon' => 'bi-facebook', 'is_active' => 1]],
        'normalizedUrl' => 'https://example.com/',
        'query' => 'penang',
    ];
    $arguments = [];
    foreach (array_slice($method->getParameters(), 0, $method->getNumberOfRequiredParameters()) as $parameter) {
        if (array_key_exists($parameter->getName(), $byName)) {
            $arguments[] = $byName[$parameter->getName()];
            continue;
        }
        $type = (string) $parameter->getType();
        $arguments[] = match (ltrim($type, '?')) {
            'int' => 1,
            'string' => 'x',
            'bool' => false,
            'array' => [],
            'DateTimeInterface', 'DateTimeImmutable' => new DateTimeImmutable(),
            StatsFilter::class => new StatsFilter(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-07')),
            default => throw new RuntimeException("No test value for {$method->class}::{$method->name}() parameter \${$parameter->getName()} ($type)"),
        };
    }

    return $arguments;
};

return [
    'every class file declares strict types and holds the class its path names' => function () use ($classFiles): void {
        foreach ($classFiles() as $path => $class) {
            check(str_contains((string) file_get_contents($path), 'declare(strict_types=1);'), "$path must declare strict types.");
            check(class_exists($class), "$path must define $class.");
        }
    },
    'every exception class extends AppException' => function () use ($classFiles): void {
        $exceptions = 0;
        foreach ($classFiles() as $class) {
            if (is_subclass_of($class, Throwable::class)) {
                check($class === AppException::class || is_subclass_of($class, AppException::class), "$class must extend AppException.");
                $exceptions++;
            }
        }
        check_same(5, $exceptions, 'Expected AppException and its four subclasses.');
    },
    'every class that runs SQL catches PDOException' => function () use ($classFiles, $runsSql, $codeOf): void {
        $found = [];
        foreach ($classFiles() as $path => $class) {
            $source = $codeOf($path);
            if ($runsSql($source)) {
                check(str_contains($source, 'catch (PDOException'), "$class runs SQL but never catches PDOException.");
                $found[] = $class;
            }
        }
        check_same([
            'App\Database', 'App\Repository\AdminRepository', 'App\Repository\PageRepository',
            'App\Repository\PlatformRepository', 'App\Repository\ShareClickRepository', 'App\Security\LoginThrottle',
            'App\Stats\ShareStats', 'App\Support\DemoSeeder', 'App\Support\SqlFile',
        ], $found, 'The scan must find every database class (update this list when adding one).');
    },
    'every public method of every database class turns a driver error into DatabaseException' => function () use ($classFiles, $argumentsFor): void {
        $noDatabase = ['App\Security\LoginThrottle::windowMinutes']; // returns a setting; never touches the database
        $checked = 0;
        foreach ($classFiles() as $class) {
            $constructor = (new ReflectionClass($class))->getConstructor();
            $first = $constructor?->getParameters()[0] ?? null;
            if ($first === null || (string) $first->getType() !== PDO::class) {
                continue; // not a class that is given a database connection
            }
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = "$class::{$method->getName()}";
                if ($method->isStatic() || $method->isConstructor() || $method->class !== $class || in_array($name, $noDatabase, true)) {
                    continue;
                }
                $object = new $class(new FailingPdo());
                $e = check_throws(DatabaseException::class, static function () use ($method, $object, $argumentsFor): void {
                    $result = $method->invokeArgs($object, $argumentsFor($method));
                    if ($result instanceof Traversable) {
                        iterator_to_array($result); // generators only run when iterated
                    }
                });
                check($e->getPrevious() instanceof PDOException, "$name must keep the PDOException as the previous exception.");
                check(str_starts_with($e->getMessage(), 'Database error while trying to '), "$name: unexpected message \"{$e->getMessage()}\".");
                $checked++;
            }
        }
        check($checked >= 32, "Expected to check every database method, only checked $checked.");
    },
    'the classes without try/catch are exactly the pure ones, and they do no I/O' => function () use ($classFiles, $pureClasses, $runsSql, $codeOf): void {
        $withoutCatch = [];
        foreach ($classFiles() as $path => $class) {
            $source = $codeOf($path);
            if (!str_contains($source, 'catch (')) {
                $withoutCatch[] = $class;
                check(!$runsSql($source), "$class has no try/catch but runs SQL.");
                check(preg_match('/\b(session_start|file_get_contents|fopen|random_bytes|curl_exec)\s*\(/', $source) !== 1, "$class has no try/catch but does I/O.");
            }
        }
        sort($withoutCatch);
        $expected = $pureClasses;
        sort($expected);
        check_same($expected, $withoutCatch, 'A class without try/catch must be pure and listed here and in docs/DETAILS.md.');
    },
    'every class states its error handling in its class comment, and the statement matches the code' => function () use ($classFiles, $codeOf): void {
        $checked = 0;
        foreach ($classFiles() as $path => $class) {
            $comment = (string) (new ReflectionClass($class))->getDocComment();
            check(str_contains($comment, 'Error handling:'), "$class: its class comment needs an \"Error handling:\" line (see docs/DETAILS.md).");
            // The statement runs from the last "Error handling:" to the end of the comment or the first @tag.
            $statement = preg_replace('/\s*\*\s*/', ' ', substr($comment, (int) strrpos($comment, 'Error handling:')));
            $statement = strtolower((string) preg_replace('#\s+(?:/$|@\w).*$#s', '', (string) $statement));
            $catches = str_contains($codeOf($path), 'catch (');

            if (is_subclass_of($class, Throwable::class)) {
                check(str_contains($statement, 'thrown by'), "$class: say which classes throw it and how it is answered.");
            } elseif ($catches) {
                check(!str_contains($statement, 'nothing to catch'), "$class catches errors, but its comment says it has nothing to catch.");
                check(preg_match('/\b(catch|catches|wrapped|becomes|rolls back)\b/', $statement) === 1, "$class: say what it catches and what it throws instead.");
            } else {
                check(str_contains($statement, 'nothing to catch'), "$class has no try/catch, so its comment must say it has nothing to catch, and why.");
            }
            $checked++;
        }
        check($checked >= 27, "Expected to check every class in src/, only checked $checked.");
    },
];
