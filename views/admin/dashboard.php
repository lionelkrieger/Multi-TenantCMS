<?php
/** @var array{totalOrganizations:int,totalUsers:int} $metrics */
/** @var array<int, \App\Models\Organization> $recentOrganizations */
/** @var array<int, \App\Models\User> $recentUsers */

ob_start();
?>
<section class="admin-dashboard">
    <header class="page-header">
        <h2>Master Admin Overview</h2>
        <p>High-level snapshot of organizations and user growth.</p>
    </header>

    <div class="stat-grid">
        <article class="stat-card">
            <p class="label">Total Organizations</p>
            <p class="value"><?= number_format((int) ($metrics['totalOrganizations'] ?? 0)) ?></p>
        </article>
        <article class="stat-card">
            <p class="label">Total Users</p>
            <p class="value"><?= number_format((int) ($metrics['totalUsers'] ?? 0)) ?></p>
        </article>
    </div>

    <div class="panel-grid">
        <section class="panel">
            <h3>Newest Organizations</h3>
            <?php if ($recentOrganizations === []): ?>
                <p>No organizations found yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($recentOrganizations as $organization): ?>
                        <li>
                            <strong><?= sanitize($organization->name) ?></strong>
                            <small>
                                Added <?= sanitize(date('M j, Y', strtotime($organization->createdAt))) ?>
                                <?php if (!empty($organization->customDomain)): ?>
                                    • Domain: <?= sanitize($organization->customDomain) ?>
                                <?php endif; ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h3>Newest Users</h3>
            <?php if ($recentUsers === []): ?>
                <p>No users found yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($recentUsers as $user): ?>
                        <li>
                            <strong><?= sanitize($user->name ?? $user->email) ?></strong>
                            <small>
                                Joined <?= sanitize(date('M j, Y', strtotime($user->createdAt))) ?>
                                • <?= sanitize(ucwords(str_replace('_', ' ', $user->userType))) ?>
                            </small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php
$content = ob_get_clean();
$title = 'Master Admin Dashboard';
require view_path('layouts/base.php');
