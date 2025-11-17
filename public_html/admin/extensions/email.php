<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/includes/bootstrap.php';

use App\Support\Database;

// In a real application, the organization ID would come from the session.
$organizationId = 'org-123'; // Hardcoded for now

$pdo = Database::connection();

$pendingStmt = $pdo->prepare('SELECT COUNT(*) FROM email_queue WHERE organization_id = :org_id AND status IN ("pending", "retrying")');
$pendingStmt->execute([':org_id' => $organizationId]);
$pendingCount = $pendingStmt->fetchColumn();

$failedStmt = $pdo->prepare('SELECT COUNT(*) FROM email_queue WHERE organization_id = :org_id AND status = "failed"');
$failedStmt->execute([':org_id' => $organizationId]);
$failedCount = $failedStmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Extension Diagnostics</title>
    <style>
        body { font-family: sans-serif; }
        .metric { font-size: 2em; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Email Extension Diagnostics</h1>
    <h2>Organization: <?php echo htmlspecialchars($organizationId); ?></h2>

    <div>
        <h3>Pending Emails</h3>
        <p class="metric"><?php echo $pendingCount; ?></p>
    </div>

    <div>
        <h3>Failed Emails</h3>
        <p class="metric"><?php echo $failedCount; ?></p>
    </div>
</body>
</html>
