<?php

return [
    'extensions.manage' => ['master_admin'],
    'payments:manage' => ['master_admin', 'org_admin'],
    'payments:view' => ['master_admin', 'org_admin', 'employee'],
    'analytics:read' => ['master_admin', 'org_admin'],
    'payfast.manage_credentials' => ['master_admin', 'org_admin'],
    'payfast.view_transactions' => ['master_admin', 'org_admin', 'employee'],
    'email.manage' => ['master_admin', 'org_admin'],
];
