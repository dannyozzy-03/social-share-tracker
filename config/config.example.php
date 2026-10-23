<?php

// Defaults. Override any of them in config/config.php (bin/install.php creates it).
return [
    'app' => [
        // Admin area name.
        'name' => 'Social Share Tracker',
        // Front-end publication name.
        'site_name' => 'Ajaklah News',
        // Public URL of public/, no trailing slash; empty = auto-detect.
        // e.g. 'http://localhost:8000' (php -S) or 'http://localhost/social-share-tracker' (XAMPP).
        'url' => 'http://localhost:8000',
        'timezone' => 'Asia/Kuala_Lumpur',
        // Keys the visitor IP hash (raw IPs are never stored). Change it.
        'secret' => 'change-me-to-a-long-random-string',
        'debug' => false,
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'social_share_tracker',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    'tracking' => [
        // Extra trackable hosts; app.url's host is always allowed.
        'allowed_hosts' => [],
        // Ignore repeat clicks (same button, page, visitor) within this window.
        'dedupe_seconds' => 10,
        // Clicks per visitor per minute before HTTP 429.
        'max_per_minute' => 30,
    ],

    'admin' => [
        'session_idle_minutes' => 30,
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
    ],
];
