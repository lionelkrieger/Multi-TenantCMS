<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Install\Installer;
use App\Install\InstallerConfig;
use App\Install\InstallerException;

$installer = new Installer();

if ($installer->isAlreadyInstalled()) {
	fwrite(STDOUT, "Application is already installed. Remove app/config/database.php to rerun.\n");
	exit(0);
}

$inputs = [
	'db_host' => cli_prompt('Database host', 'localhost'),
	'existing_username' => cli_prompt('Existing MySQL username'),
	'existing_password' => cli_prompt('Existing MySQL password'),
	'app_db_name' => cli_prompt('Application database name', 'property_management'),
	'app_db_username' => cli_prompt('Application database username', 'prop_mgmt_user'),
	'app_db_password' => cli_prompt('Application database password (leave blank to auto-generate)', ''),
	'master_admin_email' => cli_prompt('Master admin email'),
	'master_admin_password' => cli_prompt('Master admin password (8+ characters)'),
];

try {
	$config = InstallerConfig::fromArray($inputs);
	$result = $installer->install($config);

	fwrite(STDOUT, "\nInstallation finished successfully.\n");
	if ($result->appPasswordGenerated) {
		fwrite(STDOUT, sprintf("Generated application DB password: %s\n", $result->appDbPassword));
	}
	fwrite(STDOUT, sprintf("Master admin email: %s\n", $result->masterAdminEmail));
	fwrite(STDOUT, "You can now log in via public_html/login.php.\n");
} catch (InstallerException $exception) {
	fwrite(STDERR, 'Installation failed: ' . $exception->getMessage() . "\n");
	exit(1);
}

function cli_prompt(string $label, string $default = ''): string
{
	$prompt = $default !== '' ? sprintf('%s [%s]: ', $label, $default) : sprintf('%s: ', $label);
	$input = readline($prompt);
	if ($input === '' && $default !== '') {
		return $default;
	}
	return trim($input);
}
