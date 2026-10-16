<?php
// Copy to config.php and set a unique database password for your environment.
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'onecar',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'frontend_origin' => getenv('FRONTEND_ORIGIN') ?: 'http://localhost:5173',
    'session_samesite' => getenv('SESSION_SAMESITE') ?: 'Lax',
    'session_secure' => filter_var(getenv('SESSION_SECURE') ?: '0', FILTER_VALIDATE_BOOLEAN),
    'demo_mode' => true,
    'commission_rate' => 0.15,
    'timezone' => 'Asia/Kathmandu',
];
