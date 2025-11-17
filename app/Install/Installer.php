<?php

declare(strict_types=1);

namespace App\Install;

use PDO;
use PDOException;
use Throwable;

final class Installer
{
    private const CONFIG_FILENAME = 'database.php';

    public function isAlreadyInstalled(): bool
    {
        return file_exists($this->configFilePath());
    }

    public function install(InstallerConfig $config): InstallerResult
    {
        $this->logInstallAttempt($config);

        if ($this->isAlreadyInstalled()) {
            throw new InstallerException('Application is already installed.');
        }

        try {
            $systemPdo = $this->createSystemConnection($config);
            $this->createDatabase($systemPdo, $config->appDbName);
            $this->createApplicationUser($systemPdo, $config);
        } catch (PDOException $exception) {
            $wrapped = new InstallerException('Failed to prepare database objects: ' . $exception->getMessage(), 0, $exception);
            $this->logInstallFailure($wrapped);
            throw $wrapped;
        }

        try {
            $appPdo = $this->createAppConnection($config);
            $this->applySchema($appPdo);
            $this->createMasterAdmin($appPdo, $config);
        } catch (Throwable $exception) {
            $wrapped = new InstallerException('Failed to initialize application schema: ' . $exception->getMessage(), 0, $exception);
            $this->logInstallFailure($wrapped);
            throw $wrapped;
        }

        try {
            $this->writeDatabaseConfig($config);
        } catch (InstallerException $exception) {
            $this->logInstallFailure($exception);
            throw $exception;
        }

        \logger('Installation completed successfully.');
        $this->logInstallSuccess($config);

        return new InstallerResult(
            $config->appPasswordWasGenerated(),
            $config->appDbPassword(),
            $config->masterAdminEmail
        );
    }

    private function createSystemConnection(InstallerConfig $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;charset=utf8mb4', $config->dbHost);
        $pdo = new PDO($dsn, $config->existingUsername, $config->existingPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        return $pdo;
    }

    private function createAppConnection(InstallerConfig $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config->dbHost, $config->appDbName);
        $pdo = new PDO($dsn, $config->appDbUsername, $config->appDbPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    }

    private function createDatabase(PDO $pdo, string $dbName): void
    {
        $sql = sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName);
        $pdo->exec($sql);
    }

    private function createApplicationUser(PDO $pdo, InstallerConfig $config): void
    {
        $host = $this->determineUserHost($config->dbHost);
        $username = $pdo->quote($config->appDbUsername);
        $hostQuoted = $pdo->quote($host);
        $passwordQuoted = $pdo->quote($config->appDbPassword());

        $pdo->exec(sprintf('CREATE USER IF NOT EXISTS %s@%s IDENTIFIED BY %s', $username, $hostQuoted, $passwordQuoted));
        $pdo->exec(sprintf('ALTER USER %s@%s IDENTIFIED BY %s', $username, $hostQuoted, $passwordQuoted));
        $grantSql = sprintf('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX ON `%s`.* TO %s@%s', $config->appDbName, $username, $hostQuoted);
        $pdo->exec($grantSql);
        $pdo->exec('FLUSH PRIVILEGES');
    }

    private function applySchema(PDO $pdo): void
    {
        foreach (SqlSchema::statements() as $statement) {
            $pdo->exec($statement);
        }
    }

    private function createMasterAdmin(PDO $pdo, InstallerConfig $config): void
    {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $check->execute(['email' => $config->masterAdminEmail]);

        if ((int) $check->fetchColumn() > 0) {
            throw new InstallerException('A user with the provided master admin email already exists.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (id, email, password_hash, name, user_type, status, created_at) VALUES (:id, :email, :password_hash, :name, :user_type, :status, NOW())'
        );

        $insert->execute([
            'id' => \generate_uuid_v4(),
            'email' => $config->masterAdminEmail,
            'password_hash' => \Auth::hashPassword($config->masterAdminPassword),
            'name' => 'Master Admin',
            'user_type' => 'master_admin',
            'status' => 'active',
        ]);
    }

    private function writeDatabaseConfig(InstallerConfig $config): void
    {
        $configArray = $config->toDatabaseConfig();
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($configArray, true) . ';\n';
        $path = $this->configFilePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new InstallerException(sprintf('Unable to create config directory at %s.', $directory));
        }

        if (file_exists($path) && !is_writable($path)) {
            throw new InstallerException(sprintf('Existing config file at %s is not writable.', $path));
        }

        $bytes = @file_put_contents($path, $contents, LOCK_EX);
        if ($bytes === false) {
            throw new InstallerException(sprintf('Unable to write database config file at %s.', $path));
        }

        $this->applyConfigPermissions($path);
        \app_logger()->info('Database configuration file written.', ['path' => $path]);
    }

    private function configFilePath(): string
    {
        return \config_path(self::CONFIG_FILENAME);
    }

    private function determineUserHost(string $dbHost): string
    {
        return $dbHost === 'localhost' ? 'localhost' : '%';
    }

    private function applyConfigPermissions(string $path): void
    {
        $chmodSucceeded = @chmod($path, 0600);
        if ($chmodSucceeded === false) {
            \app_logger()->warning('Unable to set database config permissions to 0600.', ['file' => $path]);
            return;
        }

        clearstatcache(true, $path);
        $perms = @substr(sprintf('%o', fileperms($path)), -3);
        if ($perms !== false && $perms !== '600') {
            \app_logger()->warning('Database config permissions are not 0600.', [
                'file' => $path,
                'permissions' => $perms,
            ]);
        }
    }

    private function logInstallAttempt(InstallerConfig $config): void
    {
        \app_logger()->info('Installer run started.', [
            'db_host' => $config->dbHost,
            'database' => $config->appDbName,
            'app_db_username' => $config->appDbUsername,
            'master_admin_email' => $config->masterAdminEmail,
        ]);
    }

    private function logInstallSuccess(InstallerConfig $config): void
    {
        \app_logger()->info('Installer run completed successfully.', [
            'database' => $config->appDbName,
            'app_db_username' => $config->appDbUsername,
            'master_admin_email' => $config->masterAdminEmail,
        ]);
    }

    private function logInstallFailure(InstallerException $exception): void
    {
        \app_logger()->error('Installer run failed.', [
            'error' => $exception->getMessage(),
        ]);
    }
}
