<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;
use App\Support\RequestValidator;
use finfo;

final class OrganizationSettingsController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly UserRepository $users
    ) {
    }

    public function show(string $organizationId): void
    {
        $this->authorize($organizationId);

        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not found.']);
            return;
        }

        $flash = ['success' => null, 'error' => null];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handlePost($organization, $_POST, $_FILES);
            if ($result['redirect'] !== null) {
                $this->redirect($result['redirect']);
            }

            if ($result['message'] !== null) {
                $flash[$result['type']] = $result['message'];
            }

            $organization = $this->organizations->findById($organizationId) ?? $organization;
        } elseif (isset($_GET['saved'])) {
            $flash['success'] = 'Branding updated.';
        }

        $this->render('org/settings', [
            'organization' => $organization,
            'flash' => $flash,
            'csrfToken' => \CSRF::token(),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array<string, mixed>> $files
     * @return array{redirect: ?string, type: 'success'|'error', message: ?string}
     */
    private function handlePost(Organization $organization, array $input, array $files): array
    {
        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Invalid security token. Refresh and try again.',
            ];
        }

        $primary = RequestValidator::hexColor($input['primary_color'] ?? '') ?? $organization->primaryColor;
        $secondary = RequestValidator::hexColor($input['secondary_color'] ?? '') ?? $organization->secondaryColor;
        $accent = RequestValidator::hexColor($input['accent_color'] ?? '') ?? $organization->accentColor;
        $fontFamily = trim((string) ($input['font_family'] ?? $organization->fontFamily));
        $fontFamily = $fontFamily === '' ? $organization->fontFamily : $fontFamily;

        if (strlen($fontFamily) > 80) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Font family must be 80 characters or fewer.',
            ];
        }

        $customCss = trim((string) ($input['custom_css'] ?? $organization->customCss ?? ''));
        if (strlen($customCss) > 5000) {
            return [
                'redirect' => null,
                'type' => 'error',
                'message' => 'Custom CSS is limited to 5000 characters.',
            ];
        }

        $payload = [
            'primary_color' => strtoupper($primary),
            'secondary_color' => strtoupper($secondary),
            'accent_color' => strtoupper($accent),
            'font_family' => $fontFamily,
            'show_branding' => isset($input['show_branding']) ? 1 : 0,
            'custom_css' => $customCss === '' ? null : $customCss,
        ];

        $logoUpload = $files['logo'] ?? null;
        if ($logoUpload !== null && ($logoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $storedPath = $this->storeLogo($organization, $logoUpload);
            if ($storedPath === null) {
                return [
                    'redirect' => null,
                    'type' => 'error',
                    'message' => 'Logo upload failed. Use PNG, JPG, WEBP, or SVG up to 2MB.',
                ];
            }

            $payload['logo_url'] = $storedPath;
        } elseif (isset($input['remove_logo'])) {
            $this->deleteLogo($organization->logoUrl);
            $payload['logo_url'] = null;
        }

        $this->organizations->updateBranding($organization->id, $payload);

        return [
            'redirect' => '/org/settings.php?id=' . urlencode($organization->id) . '&saved=1',
            'type' => 'success',
            'message' => null,
        ];
    }

    private function authorize(string $organizationId): User
    {
        if (!\Auth::check()) {
            $this->redirect('/login.php');
        }

        $userId = \Auth::id();
        if ($userId === null) {
            $this->redirect('/login.php');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            $this->redirect('/login.php');
        }

        if ($user->userType === 'master_admin') {
            return $user;
        }

        if (in_array($user->userType, ['org_admin', 'employee'], true) && $user->organizationId === $organizationId) {
            return $user;
        }

        http_response_code(403);
        $this->render('errors/forbidden');
        exit;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function storeLogo(Organization $organization, array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if (($file['size'] ?? 0) > 2_000_000) {
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: 'application/octet-stream';
        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        if (!isset($allowed[$mime])) {
            return null;
        }

        $directory = storage_path('logos/' . $organization->id);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        $filename = 'logo_' . time() . '.' . $allowed[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!@move_uploaded_file($tmpPath, $destination)) {
            return null;
        }

        @chmod($destination, 0644);

        $this->deleteLogo($organization->logoUrl, $destination);

        $relativePath = 'logos/' . $organization->id . '/' . $filename;
        return $relativePath;
    }

    private function deleteLogo(?string $relativePath, ?string $skipPath = null): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $absolute = storage_path($relativePath);
        if ($absolute === $skipPath) {
            return;
        }

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
