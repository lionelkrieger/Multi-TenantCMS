<?php

declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Extensions\ExtensionRegistry;

$usage = static function (): void {
    echo "Usage: php cli/extensions.php <command>\n";
    echo "Commands:\n";
    echo "  sync     Discover extension manifests and upsert metadata.\n";
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
        default:
            echo "Unknown command: {$command}" . PHP_EOL;
            $usage();
            exit(1);
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
