<?php
ob_start();

$warnings = $warnings ?? [];
$recentProperties = $recentProperties ?? [];

$customDomain = $organization->customDomain ? sanitize($organization->customDomain) : 'Not configured';
$domainStatus = $organization->domainVerified ? 'Verified' : 'Pending';
$sslStatus = sanitize($organization->sslCertificateStatus);
$propertyCount = $stats['property_count'] ?? 0;
?>
<section class="org-dashboard">
    <header class="dashboard-header">
        <div>
            <p class="dashboard-eyebrow">Organization</p>
            <h2><?= sanitize($organization->name) ?> Dashboard</h2>
            <p class="muted">Created <?= sanitize(date('M j, Y', strtotime($organization->createdAt))) ?> · Last updated <?= sanitize(date('M j, Y', strtotime($organization->updatedAt))) ?></p>
        </div>
        <div class="cta-group">
            <a href="/org/properties.php" class="btn btn-primary">Manage Properties</a>
            <a href="/org/settings.php" class="btn btn-secondary">Branding & Settings</a>
        </div>
    </header>

    <?php if (!empty($warnings)) : ?>
        <div class="alert alert-warning">
            <h3>Attention needed</h3>
            <ul>
                <?php foreach ($warnings as $warning) : ?>
                    <li><?= sanitize($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <p class="stat-label">Active Properties</p>
            <p class="stat-value"><?= sanitize((string) $propertyCount) ?></p>
            <p class="muted">Add or update properties to publish public listings.</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Custom Domain</p>
            <p class="stat-value"><?= $customDomain ?></p>
            <p class="muted">Status: <?= sanitize($domainStatus) ?> · SSL: <?= $sslStatus ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Brand Palette</p>
            <div class="brand-swatches">
                <span title="Primary" style="background-color: <?= sanitize($organization->primaryColor) ?>"></span>
                <span title="Secondary" style="background-color: <?= sanitize($organization->secondaryColor) ?>"></span>
                <span title="Accent" style="background-color: <?= sanitize($organization->accentColor) ?>"></span>
            </div>
            <p class="muted">Font: <?= sanitize($organization->fontFamily) ?></p>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h3>Recent properties</h3>
            <a href="/org/properties.php" class="link">View all</a>
        </div>
        <?php if (empty($recentProperties)) : ?>
            <p class="muted">No properties yet. Use the button above to create your first property.</p>
        <?php else : ?>
            <ul class="property-list">
                <?php foreach ($recentProperties as $property) : ?>
                    <li>
                        <div>
                            <strong><?= sanitize($property->name) ?></strong>
                            <p class="muted">Added <?= sanitize(date('M j, Y', strtotime($property->createdAt))) ?></p>
                            <?php if ($property->address) : ?>
                                <p><?= sanitize($property->address) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="list-actions">
                            <a href="/org/property-edit.php?id=<?= urlencode($property->id) ?>" class="btn btn-link">Edit</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>
<?php
$content = ob_get_clean();
$title = 'Organization Dashboard';
require view_path('layouts/base.php');
