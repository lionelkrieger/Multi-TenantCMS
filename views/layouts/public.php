<?php
/** @var \App\Models\Organization|null $organization */
$brand = $organization ?? null;
$primary = $brand?->primaryColor ?? '#0066cc';
$secondary = $brand?->secondaryColor ?? '#f8f9fa';
$accent = $brand?->accentColor ?? '#ff7043';
$fontFamily = $brand?->fontFamily ?? 'Inter, -apple-system, BlinkMacSystemFont, sans-serif';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= sanitize($title ?? ($brand?->name ?? 'Properties')) ?></title>
        <link rel="stylesheet" href="/assets/css/app.css">
        <style>
            :root {
                --brand-primary: <?= sanitize($primary) ?>;
                --brand-secondary: <?= sanitize($secondary) ?>;
                --brand-accent: <?= sanitize($accent) ?>;
            }

            body.public-layout {
                font-family: <?= sanitize($fontFamily) ?>;
                background: var(--brand-secondary);
                color: #1f2933;
                margin: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .public-header {
                background: var(--brand-primary);
                color: #ffffff;
                padding: 2rem;
                display: flex;
                align-items: center;
                gap: 1.5rem;
                flex-wrap: wrap;
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            }

            .public-header img {
                max-height: 64px;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.15);
                padding: 0.5rem;
            }

            .public-header h1 {
                margin: 0.25rem 0 0;
                font-size: 2rem;
            }

            .public-header .eyebrow {
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-size: 0.75rem;
                opacity: 0.9;
                margin: 0;
            }

            .eyebrow {
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-size: 0.75rem;
                margin: 0 0 0.5rem;
                color: var(--brand-accent);
            }

            .muted {
                color: #6b7280;
            }

            .public-content {
                flex: 1;
                width: min(960px, 100%);
                margin: 0 auto;
                padding: 2rem 1.5rem 3rem;
            }

            .card {
                background: #fff;
                border-radius: 1.25rem;
                padding: 1.75rem;
                box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
                margin-bottom: 1.5rem;
            }

            .hero {
                background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(0,0,0,0));
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .public-footer {
                padding: 1.5rem;
                text-align: center;
                background: #fff;
                border-top: 1px solid rgba(0, 0, 0, 0.05);
                font-size: 0.9rem;
                color: #6c757d;
            }

            .cta-primary {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: var(--brand-accent);
                color: #fff;
                border-radius: 999px;
                padding: 0.75rem 1.5rem;
                text-decoration: none;
                font-weight: 600;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .cta-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
            }

            .property-grid {
                list-style: none;
                padding: 0;
                margin: 0;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 1.5rem;
            }

            .property-grid .card {
                margin: 0;
            }

            .cta {
                margin-top: 1.5rem;
            }

            .pagination {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 1rem;
                margin-top: 1.5rem;
            }

            .pagination a {
                color: var(--brand-primary);
                text-decoration: none;
                font-weight: 600;
            }

            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                border: 0;
            }
        </style>
        <?php if (!empty($brand?->customCss)): ?>
            <style><?= sanitize($brand->customCss) ?></style>
        <?php endif; ?>
    </head>
    <body class="public-layout">
        <header class="public-header">
            <?php if (!empty($brand?->logoUrl)): ?>
                <img src="/serve-logo.php?logo=<?= urlencode($brand->logoUrl) ?>" alt="<?= sanitize($brand->name) ?> logo">
            <?php endif; ?>
            <div>
                <p class="eyebrow"><?= sanitize($brand?->name ?? 'Featured listings') ?></p>
                <h1><?= sanitize($title ?? ($brand?->name ?? 'Properties')) ?></h1>
            </div>
        </header>
        <main class="public-content">
            <?= $content ?? '' ?>
        </main>
        <footer class="public-footer">
            <p>&copy; <?= sanitize((string) date('Y')) ?> <?= sanitize($brand?->name ?? 'Our Company') ?></p>
            <?php if (($brand->showBranding ?? true) === true): ?>
                <p class="muted">Powered by Multi-Tenant Property Management</p>
            <?php endif; ?>
        </footer>
    </body>
</html>
