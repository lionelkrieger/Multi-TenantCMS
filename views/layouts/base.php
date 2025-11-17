<?php
/** @var \App\Models\Organization|null $organization */
$brandOrg = $organization ?? null;
if ($brandOrg === null && function_exists('resolve_organization_from_request')) {
    $brandOrg = resolve_organization_from_request();
}

$brandPrimary = $brandOrg?->primaryColor ?? '#1d4ed8';
$brandSecondary = $brandOrg?->secondaryColor ?? '#0f172a';
$brandAccent = $brandOrg?->accentColor ?? '#f97316';
$brandFont = $brandOrg?->fontFamily ?? "'Inter', sans-serif";
$logoSrc = !empty($brandOrg?->logoUrl)
    ? '/serve-logo.php?org=' . urlencode($brandOrg->id) . '&v=' . urlencode($brandOrg->updatedAt)
    : null;
$orgQuery = $brandOrg ? '?id=' . urlencode($brandOrg->id) : '';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($title ?? 'Multi-Tenant CMS') ?></title>
        <link rel="stylesheet" href="/assets/css/app.css">
        <style>
            :root {
                --brand-primary: <?= sanitize($brandPrimary) ?>;
                --brand-secondary: <?= sanitize($brandSecondary) ?>;
                --brand-accent: <?= sanitize($brandAccent) ?>;
                --brand-font: <?= sanitize($brandFont) ?>;
            }
        </style>
    </head>
    <body>
        <header class="site-header">
            <div class="brand-mark">
                <?php if ($logoSrc): ?>
                    <img src="<?= sanitize($logoSrc) ?>" alt="<?= sanitize($brandOrg->name) ?> logo">
                <?php endif; ?>
                <div>
                    <h1><?= sanitize($title ?? ($brandOrg->name ?? 'Multi-Tenant CMS')) ?></h1>
                    <?php if ($brandOrg !== null): ?>
                        <p class="muted" style="margin:0; font-size:0.85rem;">Branding applied for <?= sanitize($brandOrg->name) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (\Auth::check()): ?>
                <nav>
                    <a href="/index.php">Dashboard</a>
                    <?php if (\Auth::userType() === 'master_admin'): ?>
                        <a href="/admin/dashboard.php">Master Admin</a>
                        <a href="/admin/organizations.php">Organizations</a>
                        <a href="/admin/users.php">Users</a>
                        <a href="/admin/extensions.php">Extensions</a>
                    <?php endif; ?>
                    <a href="/org/dashboard.php<?= $orgQuery ?>">Org Dashboard</a>
                    <a href="/org/users.php<?= $orgQuery ?>">Org Users</a>
                    <a href="/org/extensions.php<?= $orgQuery ?>">Org Extensions</a>
                    <a href="/org/settings.php<?= $orgQuery ?>">Org Settings</a>
                    <a href="/logout.php">Logout</a>
                </nav>
            <?php endif; ?>
        </header>
        <main>
            <?= $content ?? '' ?>
        </main>
    </body>
</html>
