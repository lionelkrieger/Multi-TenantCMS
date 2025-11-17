<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

$connection = Database::connection();

$requirements = [
    'users' => ['id', 'email', 'password_hash', 'name', 'organization_id', 'user_type', 'status', 'created_at'],
    'organizations' => [
        'id',
        'name',
        'created_by',
        'logo_url',
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_family',
        'show_branding',
        'custom_css',
        'custom_domain',
        'domain_verified',
        'domain_verification_token',
        'domain_verified_at',
        'ssl_certificate_status',
        'ssl_certificate_expires',
        'created_at',
        'updated_at',
    ],
    'properties' => ['id', 'name', 'description', 'address', 'organization_id', 'created_at'],
    'user_property_access' => ['id', 'user_id', 'property_id', 'assigned_at'],
    'employee_org_assignments' => ['id', 'employee_user_id', 'organization_id', 'assigned_by', 'assigned_at'],
    'user_invites' => ['id', 'email', 'organization_id', 'inviter_user_id', 'invite_type', 'token', 'status', 'created_at', 'expires_at'],
    'user_flows' => ['id', 'token', 'org_id', 'property_id', 'user_id', 'current_step', 'completed_steps', 'expires_at', 'created_at'],
    'extensions' => [
        'id',
        'slug',
        'name',
        'display_name',
        'version',
    'installed_version',
        'author',
        'description',
        'homepage_url',
        'entry_point',
        'manifest_path',
    'manifest_checksum',
    'signature_status',
    'signature_vendor',
        'status',
        'allow_org_toggle',
        'requires_core_version',
        'created_at',
        'updated_at',
    ],
    'extension_settings' => ['id', 'extension_id', 'organization_id', 'key', 'value', 'created_at', 'updated_at'],
    'extension_permissions' => ['id', 'extension_id', 'permission', 'created_at'],
    'extension_hooks' => ['id', 'extension_id', 'hook_type', 'hook_name', 'metadata', 'created_at'],
    'extension_routes' => ['id', 'extension_id', 'surface', 'method', 'path', 'capability', 'metadata', 'created_at'],
    'extension_panels' => ['id', 'extension_id', 'panel_key', 'title', 'component', 'schema_path', 'permissions', 'visible_to', 'org_toggle', 'sort_order', 'metadata', 'created_at'],
];

$missing = [];

foreach ($requirements as $table => $columns) {
    $stmt = $connection->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
    if ($stmt === false) {
        $missing[$table] = $columns;
        continue;
    }

    $existing = array_map(static fn (array $row) => $row['Field'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    $diff = array_values(array_diff($columns, $existing));

    if ($diff !== []) {
        $missing[$table] = $diff;
    }
}

if ($missing !== []) {
    echo "Schema check failed:\n";
    foreach ($missing as $table => $cols) {
        echo sprintf('- %s missing columns: %s%s', $table, implode(', ', $cols), PHP_EOL);
    }
    exit(1);
}

echo "Schema check passed." . PHP_EOL;
