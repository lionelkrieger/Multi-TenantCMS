<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Extensions\ExtensionRegistry;

$usage = static function (): void {
    echo "Usage: php cli/extensions.php <command> [arguments]\n";
    echo "Commands:\n";
    echo "  sync                         Discover extension manifests and upsert metadata.\n";
    echo "  install <slug>               Run install lifecycle for an extension.\n";
    echo "  upgrade <slug>               Run upgrade lifecycle (if needed).\n";
    echo "  uninstall <slug>             Execute uninstall lifecycle and purge metadata.\n";
    echo "  activate <slug> <orgId>      Activate an extension for an organization.\n";
    echo "  deactivate <slug> <orgId>    Deactivate an extension for an organization.\n";
    echo "  doctor [slug]                Validate manifest + metadata consistency (optional slug).\n";
};

$command = $argv[1] ?? null;
if ($command === null) {
    $usage();
    exit(1);
}

try {
    $registry = new ExtensionRegistry();

    switch ($command) {
        case 'sync':
            $registry->discover();
            echo "Extensions synced successfully." . PHP_EOL;
            break;
        case 'install':
            $slug = $argv[2] ?? null;
            if ($slug === null) {
                $usage();
                exit(1);
            }
            $registry->install($slug);
            echo sprintf('Extension %s installed.%s', $slug, PHP_EOL);
            break;
        case 'upgrade':
            $slug = $argv[2] ?? null;
            if ($slug === null) {
                $usage();
                exit(1);
            }
            $registry->upgrade($slug);
            echo sprintf('Extension %s upgraded.%s', $slug, PHP_EOL);
            break;
        case 'uninstall':
            $slug = $argv[2] ?? null;
            if ($slug === null) {
                $usage();
                exit(1);
            }
            $registry->uninstall($slug);
            echo sprintf('Extension %s uninstalled.%s', $slug, PHP_EOL);
            break;
        case 'activate':
            $slug = $argv[2] ?? null;
            $orgId = $argv[3] ?? null;
            if ($slug === null || $orgId === null) {
                $usage();
                exit(1);
            }
            $registry->activate($slug, $orgId);
            echo sprintf('Extension %s activated for %s.%s', $slug, $orgId, PHP_EOL);
            break;
        case 'deactivate':
            $slug = $argv[2] ?? null;
            $orgId = $argv[3] ?? null;
            if ($slug === null || $orgId === null) {
                $usage();
                exit(1);
            }
            $registry->deactivate($slug, $orgId);
            echo sprintf('Extension %s deactivated for %s.%s', $slug, $orgId, PHP_EOL);
            break;
        case 'doctor':
            $slug = $argv[2] ?? null;
            $reports = $registry->doctor($slug);
            foreach ($reports as $report) {
                echo sprintf("[%s] %s\n", strtoupper($report['status']), $report['slug']);
                if (isset($report['manifest_counts'])) {
                    echo sprintf(
                        "  manifest: permissions=%d events=%d commands=%d routes=%d panels=%d\n",
                        $report['manifest_counts']['permissions'],
                        $report['manifest_counts']['events'],
                        $report['manifest_counts']['commands'],
                        $report['manifest_counts']['routes'],
                        $report['manifest_counts']['panels']
                    );
                }
                echo sprintf(
                    "  db:        permissions=%d events=%d commands=%d routes=%d panels=%d\n",
                    $report['db_counts']['permissions'],
                    $report['db_counts']['events'],
                    $report['db_counts']['commands'],
                    $report['db_counts']['routes'],
                    $report['db_counts']['panels']
                );
                foreach ($report['issues'] as $issue) {
                    echo '  - ' . $issue . PHP_EOL;
                }
                echo PHP_EOL;
            }
            break;
        default:
            echo "Unknown command: {$command}" . PHP_EOL;
            $usage();
            exit(1);
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
