<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Security\Auth;

header('Cache-Control: no-store');
json_response(['authenticated' => Auth::isAuthenticated()]);
