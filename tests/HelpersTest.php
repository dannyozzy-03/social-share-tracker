<?php

declare(strict_types=1);

return [
    'local app URLs use the port serving the current request' => function (): void {
        $config = require dirname(__DIR__) . '/config/config.example.php';
        if (is_file(dirname(__DIR__) . '/config/config.php')) {
            $config = array_replace_recursive($config, require dirname(__DIR__) . '/config/config.php');
        }
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);

        App\Config::load(array_replace_recursive($config, ['app' => ['url' => 'http://localhost:8000']]));
        $_SERVER['HTTP_HOST'] = 'localhost:8111';

        try {
            check_same('http://localhost:8111/admin/', app_url('admin/'));
        } finally {
            App\Config::load($config);
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
        }
    },
    'non-local app URLs keep their configured authority' => function (): void {
        $config = require dirname(__DIR__) . '/config/config.example.php';
        if (is_file(dirname(__DIR__) . '/config/config.php')) {
            $config = array_replace_recursive($config, require dirname(__DIR__) . '/config/config.php');
        }
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);

        App\Config::load(array_replace_recursive($config, ['app' => ['url' => 'https://example.com:8443/app']]));
        $_SERVER['HTTP_HOST'] = 'example.com:8111';

        try {
            check_same('https://example.com:8443/app/admin/', app_url('admin/'));
        } finally {
            App\Config::load($config);
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
        }
    },
];
