<?php

declare(strict_types=1);

return [
    'name' => 'Multi-Tenant Property Management',
    'env' => 'local',
    'debug' => true,
    'timezone' => 'UTC',
    'platform_domain' => 'platform.localhost',
    'primary_domain' => 'platform.localhost',
    'default_org_path' => '/org',
    'log_path' => __DIR__ . '/../logs/application.log',
    'upload_path' => __DIR__ . '/../uploads',
    'encryption_key' => getenv('APP_KEY') ?: 'base64:YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXo=',
    'csrf_token_length' => 32,
    'session' => [
        'name' => 'mtpm_session',
        'cookie_lifetime' => 7200,
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]
];
