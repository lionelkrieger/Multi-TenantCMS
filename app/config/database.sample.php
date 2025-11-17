<?php

declare(strict_types=1);

return [
    'host' => 'localhost',
    'database' => 'property_management',
    'username' => 'prop_mgmt_user',
    'password' => 'change_me',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
