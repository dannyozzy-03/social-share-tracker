<?php

declare(strict_types=1);

// Each *Test.php returns [name => closure]; a test fails when it throws.

require dirname(__DIR__) . '/src/bootstrap.php';

function check(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function check_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(trim($message . "\n    expected: " . var_export($expected, true) . "\n    actual:   " . var_export($actual, true)));
    }
}

/** @param class-string<Throwable> $class */
function check_throws(string $class, callable $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($e instanceof $class, "Expected $class, got " . $e::class . ': ' . $e->getMessage());

        return $e;
    }
    throw new RuntimeException("Expected $class to be thrown");
}

$passed = 0;
$failures = [];

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    $suite = basename($file, '.php');
    foreach (require $file as $name => $test) {
        try {
            $test();
            $passed++;
            echo '.';
        } catch (Throwable $e) {
            $failures[] = "$suite > $name\n    " . $e->getMessage();
            echo 'F';
        }
    }
}

echo "\n\n";
foreach ($failures as $failure) {
    echo "FAIL $failure\n\n";
}
printf("%d passed, %d failed\n", $passed, count($failures));
exit($failures === [] ? 0 : 1);
