<?php

declare(strict_types=1);

namespace App\Support;

final class CapabilityAuthorizer
{
    public function __construct(
        private readonly CapabilityRegistry $registry = new CapabilityRegistry()
    ) {
    }

    public function authorize(?string $capability): void
    {
        if ($capability === null) {
            return;
        }

        if (!\Auth::check()) {
            redirect('/login.php');
        }

        $userType = \Auth::userType();
        $allowedRoles = $this->registry->roles($capability);
        if ($allowedRoles === [] || ($userType !== null && in_array($userType, $allowedRoles, true))) {
            return;
        }

        audit_log('extensions.route.denied', [
            'capability' => $capability,
            'user_id' => \Auth::id(),
            'user_type' => $userType,
        ]);

        http_response_code(403);
        require view_path('errors/forbidden.php');
        exit;
    }
}
