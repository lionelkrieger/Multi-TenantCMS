<?php
ob_start();
?>
<section>
    <header>
        <h2><?= sanitize($organization->name) ?> Settings</h2>
        <p>Review branding, domain, and security information for this organization.</p>
    </header>

    <article>
        <h3>Branding</h3>
        <ul>
            <li>Primary color: <?= sanitize($organization->primaryColor) ?></li>
            <li>Secondary color: <?= sanitize($organization->secondaryColor) ?></li>
            <li>Accent color: <?= sanitize($organization->accentColor) ?></li>
            <li>Font family: <?= sanitize($organization->fontFamily) ?></li>
        </ul>
    </article>

    <article>
        <h3>Custom Domain</h3>
        <p>
            <?php if (!empty($organization->customDomain)): ?>
                Domain: <?= sanitize($organization->customDomain) ?> (<?= $organization->domainVerified ? 'Verified' : 'Pending verification' ?>)
            <?php else: ?>
                Custom domain not configured.
            <?php endif; ?>
        </p>
        <p>SSL status: <?= sanitize($organization->sslCertificateStatus) ?></p>
    </article>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('%s Settings', $organization->name);
require view_path('layouts/base.php');
