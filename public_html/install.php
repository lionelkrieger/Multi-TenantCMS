<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Install\Installer;
use App\Install\InstallerConfig;
use App\Install\InstallerException;

$installer = new Installer();
$errors = [];
$success = null;
$generatedPassword = null;
$lockFile = config_path('.install.lock');
$installerLocked = file_exists($lockFile);

if ($installerLocked) {
	$errors[] = 'Installer locked. Remove app/config/.install.lock (and database.php if you truly need a reinstall).';
} elseif ($installer->isAlreadyInstalled()) {
	$errors[] = 'Application is already installed. Remove app/config/database.php to rerun the installer.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
		$errors[] = 'Invalid CSRF token.';
	} else {
		try {
			$config = InstallerConfig::fromArray($_POST);
			$result = $installer->install($config);
			$success = 'Installation completed successfully. You can now log in via login.php.';
			if ($result->appPasswordGenerated) {
				$generatedPassword = $result->appDbPassword;
			}
			lock_installer($lockFile);
		} catch (InstallerException $exception) {
			$errors[] = $exception->getMessage();
			app_logger()->error('Installation failed', [
				'error' => $exception->getMessage(),
			]);
		}
	}
}

function old(string $key, string $default = ''): string
{
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		return sanitize($_POST[$key] ?? $default);
	}
	return sanitize($default);
}

$csrfToken = CSRF::token();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Install &mdash; Multi-Tenant Property Management</title>
		<style>
			body { font-family: Arial, sans-serif; background-color: #f5f6fa; margin: 0; }
			.container { max-width: 680px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 10px 40px rgba(15,23,42,.1); }
			h1 { margin-top: 0; }
			.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; }
			label { display: block; font-weight: 600; margin-bottom: 6px; }
			input { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 15px; }
			input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
			button { background-color: #2563eb; border: none; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 16px; cursor: pointer; }
			button:hover { background-color: #1d4ed8; }
			.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; }
			.alert.error { background-color: #fee2e2; color: #991b1b; }
			.alert.success { background-color: #dcfce7; color: #166534; }
			.password-hint { font-size: 13px; color: #6b7280; margin-top: 4px; }
		</style>
	</head>
	<body>
		<div class="container">
			<h1>Platform Installer</h1>
			<p>Provide existing MySQL credentials and initial admin details to bootstrap the platform.</p>

			<?php foreach ($errors as $error): ?>
				<div class="alert error"><?= sanitize($error) ?></div>
			<?php endforeach; ?>

			<?php if ($success): ?>
				<div class="alert success"><?= sanitize($success) ?></div>
				<?php if ($generatedPassword): ?>
					<div class="alert success">Generated application DB password: <strong><?= sanitize($generatedPassword) ?></strong></div>
				<?php endif; ?>
						<div class="alert success">Installer locked. To rerun intentionally, delete <code>app/config/.install.lock</code> and <code>app/config/database.php</code>.</div>
			<?php endif; ?>

			<form method="POST">
				<input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

				<h2>Database Configuration</h2>
				<div class="grid">
					<div>
						<label for="db_host">Database Host</label>
						<input id="db_host" name="db_host" value="<?= old('db_host', 'localhost') ?>" required>
					</div>
					<div>
						<label for="existing_username">Existing MySQL Username</label>
						<input id="existing_username" name="existing_username" value="<?= old('existing_username') ?>" required>
					</div>
					<div>
						<label for="existing_password">Existing MySQL Password</label>
						<input id="existing_password" name="existing_password" type="password" required>
					</div>
					<div>
						<label for="app_db_name">Application Database Name</label>
						<input id="app_db_name" name="app_db_name" value="<?= old('app_db_name', 'property_management') ?>" required>
					</div>
					<div>
						<label for="app_db_username">Application Database Username</label>
						<input id="app_db_username" name="app_db_username" value="<?= old('app_db_username', 'prop_mgmt_user') ?>" required>
					</div>
					<div>
						<label for="app_db_password">Application Database Password</label>
						<input id="app_db_password" name="app_db_password" type="password">
						<p class="password-hint">Leave blank to auto-generate a secure password.</p>
					</div>
				</div>

				<h2>Master Administrator</h2>
				<div class="grid">
					<div>
						<label for="master_admin_email">Email</label>
						<input id="master_admin_email" name="master_admin_email" type="email" value="<?= old('master_admin_email') ?>" required>
					</div>
					<div>
						<label for="master_admin_password">Password</label>
						<input id="master_admin_password" name="master_admin_password" type="password" required>
						<p class="password-hint">Minimum 8 characters.</p>
					</div>
				</div>

				<button type="submit">Install Application</button>
			</form>
		</div>
	</body>
</html>

<?php
function lock_installer(string $lockFile): void
{
	try {
		file_put_contents($lockFile, sprintf('locked:%s', date('c')));
		@chmod($lockFile, 0600);
	} catch (\Throwable $exception) {
		app_logger()->warning('Unable to write installer lock file', [
			'file' => $lockFile,
			'error' => $exception->getMessage(),
		]);
	}
}
