<?php

declare(strict_types=1);

namespace App\Install;

final class InstallerConfig
{
    public function __construct(
        public readonly string $dbHost,
        public readonly string $existingUsername,
        public readonly string $existingPassword,
        public readonly string $appDbName,
        public readonly string $appDbUsername,
        private string $appDbPassword,
        public readonly string $masterAdminEmail,
        public readonly string $masterAdminPassword,
        private bool $appPasswordGenerated
    ) {
    }

    public static function fromArray(array $input): self
    {
        $dbHost = trim($input['db_host'] ?? 'localhost');
        $existingUsername = trim($input['existing_username'] ?? '');
        $existingPassword = (string) ($input['existing_password'] ?? '');
        $appDbName = trim($input['app_db_name'] ?? 'property_management');
        $appDbUsername = trim($input['app_db_username'] ?? 'prop_mgmt_user');
        $appDbPassword = trim($input['app_db_password'] ?? '');
        $masterAdminEmail = trim(strtolower($input['master_admin_email'] ?? ''));
        $masterAdminPassword = (string) ($input['master_admin_password'] ?? '');

        $generated = false;
        if ($appDbPassword === '') {
            $appDbPassword = self::generateSecurePassword();
            $generated = true;
        }

        self::assertNotEmpty($dbHost, 'Database host is required.');
        self::assertNotEmpty($existingUsername, 'Existing MySQL username is required.');
        self::assertIdentifier($appDbName, 'Application database name may only include letters, numbers, and underscores.');
        self::assertIdentifier($appDbUsername, 'Application database username may only include letters, numbers, and underscores.');

        if (!\Validator::email($masterAdminEmail)) {
            throw new InstallerException('Master admin email is invalid.');
        }

        if (mb_strlen($masterAdminPassword) < 8) {
            throw new InstallerException('Master admin password must be at least 8 characters.');
        }

        return new self(
            $dbHost,
            $existingUsername,
            $existingPassword,
            $appDbName,
            $appDbUsername,
            $appDbPassword,
            $masterAdminEmail,
            $masterAdminPassword,
            $generated
        );
    }

    public function appDbPassword(): string
    {
        return $this->appDbPassword;
    }

    public function appPasswordWasGenerated(): bool
    {
        return $this->appPasswordGenerated;
    }

    public function toDatabaseConfig(): array
    {
        return [
            'host' => $this->dbHost,
            'database' => $this->appDbName,
            'username' => $this->appDbUsername,
            'password' => $this->appDbPassword,
            'charset' => 'utf8mb4',
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ];
    }

    private static function assertIdentifier(string $value, string $message): void
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new InstallerException($message);
        }
    }

    private static function assertNotEmpty(string $value, string $message): void
    {
        if ($value === '') {
            throw new InstallerException($message);
        }
    }

    private static function generateSecurePassword(int $length = 24): string
    {
        return substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(32))), 0, $length);
    }
}
