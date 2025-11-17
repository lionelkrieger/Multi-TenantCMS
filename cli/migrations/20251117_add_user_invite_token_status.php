<?php

declare(strict_types=1);

require __DIR__ . '/../../app/includes/bootstrap.php';

$connection = Database::connection();

$operations = [];

try {
    $connection->beginTransaction();

    $columns = $connection->query('SHOW COLUMNS FROM user_invites')->fetchAll(\PDO::FETCH_COLUMN);
    $missingToken = !in_array('token', $columns, true);
    $missingStatus = !in_array('status', $columns, true);

    if ($missingToken) {
        $operations[] = "ADD COLUMN token VARCHAR(128) NOT NULL AFTER invite_type";
    }

    if ($missingStatus) {
        $operations[] = "ADD COLUMN status ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending' AFTER token";
    }

    $indexRows = $connection->query('SHOW INDEX FROM user_invites')->fetchAll(\PDO::FETCH_ASSOC);
    $existingIndexes = array_map(static fn ($row) => $row['Key_name'], $indexRows);

    if (!in_array('idx_user_invites_email', $existingIndexes, true)) {
        $operations[] = 'ADD INDEX idx_user_invites_email (email)';
    }

    if (!in_array('idx_user_invites_status', $existingIndexes, true)) {
        $operations[] = 'ADD INDEX idx_user_invites_status (status)';
    }

    if (!in_array('idx_user_invites_token', $existingIndexes, true)) {
        $operations[] = 'ADD INDEX idx_user_invites_token (token)';
    }

    if ($operations === []) {
        echo 'user_invites table already up to date.' . PHP_EOL;
        $connection->rollBack();
        return;
    }

    $sql = 'ALTER TABLE user_invites ' . implode(",\n    ", $operations) . ';';
    $connection->exec($sql);
    $connection->commit();
    echo 'Migration complete: user_invites updated.' . PHP_EOL;
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
