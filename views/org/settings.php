<?php
$flash = $flash ?? ['success' => null, 'error' => null];
$csrfToken = $csrfToken ?? CSRF::token();
$logoSrc = !empty($organization->logoUrl)
    ? '/serve-logo.php?org=' . urlencode($organization->id) . '&v=' . urlencode($organization->updatedAt)
    : null;

ob_start();
?>
<section class="panel">
    <header class="dashboard-header">
        <div>
            <p class="dashboard-eyebrow">Organization</p>
            <h2><?= sanitize($organization->name) ?> Branding</h2>
            <p class="muted">Upload a logo, adjust your palette, and control how the platform references your brand.</p>
        </div>
        <div>
            <?php if ($logoSrc): ?>
                <img src="<?= sanitize($logoSrc) ?>" alt="<?= sanitize($organization->name) ?> logo" style="max-height:64px;border-radius:8px;">
            <?php endif; ?>
        </div>
    </header>

    <?php if (!empty($flash['success'])): ?>
        <div class="alert alert-success"><?= sanitize($flash['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
        <div class="alert alert-danger"><?= sanitize($flash['error']) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid" style="display:flex; flex-direction:column; gap:1.5rem;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">

        <section>
            <h3>Logo</h3>
            <p class="muted">Max 2MB. Accepted: PNG, JPG, WEBP, SVG.</p>
            <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                <label class="btn btn-secondary" style="cursor:pointer;">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="display:none;">
                    Choose File
                </label>
                <?php if ($logoSrc): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="remove_logo" value="1">
                        Remove current logo
                    </label>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h3>Palette</h3>
            <div class="form-grid" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem;">
                <label>
                    <span>Primary color</span>
                    <input type="color" name="primary_color" value="<?= sanitize($organization->primaryColor) ?>">
                </label>
                <label>
                    <span>Secondary color</span>
                    <input type="color" name="secondary_color" value="<?= sanitize($organization->secondaryColor) ?>">
                </label>
                <label>
                    <span>Accent color</span>
                    <input type="color" name="accent_color" value="<?= sanitize($organization->accentColor) ?>">
                </label>
                <label>
                    <span>Font family</span>
                    <input type="text" name="font_family" value="<?= sanitize($organization->fontFamily) ?>" placeholder="Inter, sans-serif">
                </label>
            </div>
        </section>

        <section>
            <h3>Custom CSS</h3>
            <textarea name="custom_css" rows="6" placeholder="/* Optional CSS overrides */" style="width:100%; border:1px solid var(--color-border); border-radius:var(--radius); padding:0.75rem; font-family:monospace;"><?= sanitize($organization->customCss ?? '') ?></textarea>
            <label class="checkbox" style="margin-top:0.5rem;">
                <input type="checkbox" name="show_branding" value="1" <?= ($organization->showBranding ?? true) ? 'checked' : '' ?>>
                Show "Powered by" footer on public pages
            </label>
        </section>

        <section>
            <h3>Custom Domain</h3>
            <p>
                <?php if (!empty($organization->customDomain)): ?>
                    Domain: <strong><?= sanitize($organization->customDomain) ?></strong>
                    (<?= $organization->domainVerified ? 'Verified' : 'Pending verification' ?>)
                <?php else: ?>
                    Custom domain not configured.
                <?php endif; ?>
            </p>
            <p>SSL status: <?= sanitize($organization->sslCertificateStatus) ?></p>
        </section>

        <div>
            <button type="submit" class="btn btn-primary">Save branding</button>
        </div>
    </form>
</section>
<?php
$content = ob_get_clean();
$title = sprintf('%s Settings', $organization->name);
require view_path('layouts/base.php');
