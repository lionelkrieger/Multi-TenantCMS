<?php
/** @var \App\Models\Organization $organization */
/** @var array<int, array{extension: \App\Models\Extension, enabled: bool, settings: array<string, mixed>, canToggle: bool}> $extensions */
/** @var array{success: ?string, error: ?string} $flash */
/** @var string $csrfToken */

ob_start();
?>
<section class="org-extensions">
    <header class="dashboard-header">
        <div>
            <p class="dashboard-eyebrow">Extensions</p>
            <h2><?= sanitize($organization->name) ?> integrations</h2>
            <p class="muted">Enable payments, analytics, and upcoming modules from the central catalogue.</p>
        </div>
        <div class="cta-group">
            <a href="/org/dashboard.php?id=<?= urlencode($organization->id) ?>" class="btn btn-secondary">Back to dashboard</a>
        </div>
    </header>

    <?php if ($flash['success']): ?>
        <div class="alert alert-success"><?= sanitize($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash['error']): ?>
        <div class="alert alert-danger"><?= sanitize($flash['error']) ?></div>
    <?php endif; ?>

    <?php if (empty($extensions)): ?>
        <p class="muted">The platform team has not published any extensions yet.</p>
    <?php else: ?>
        <div class="extension-grid">
            <?php foreach ($extensions as $row):
                /** @var \App\Models\Extension $extension */
                $extension = $row['extension'];
                $enabled = $row['enabled'];
                $settings = $row['settings'];
                $canToggle = $row['canToggle'];
                $badge = match ($extension->status) {
                    'active' => 'badge-success',
                    'installed' => 'badge-info',
                    'error' => 'badge-danger',
                    default => 'badge-muted',
                };
                ?>
                <article class="extension-card">
                    <header>
                        <div>
                            <h3><?= sanitize($extension->displayName) ?></h3>
                            <p class="muted small-text">Version <?= sanitize($extension->version) ?> · <?= sanitize($extension->slug) ?></p>
                        </div>
                        <span class="badge <?= $badge ?>"><?= sanitize(ucfirst($extension->status)) ?></span>
                    </header>

                    <?php if ($extension->description): ?>
                        <p><?= sanitize($extension->description) ?></p>
                    <?php endif; ?>

                    <section class="extension-actions">
                        <h4>Status</h4>
                        <?php if ($canToggle): ?>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="extension_slug" value="<?= sanitize($extension->slug) ?>">
                                <label for="toggle-<?= sanitize($extension->slug) ?>" class="sr-only">Toggle</label>
                                <select id="toggle-<?= sanitize($extension->slug) ?>" name="enabled">
                                    <option value="1" <?= $enabled ? 'selected' : '' ?>>Enabled</option>
                                    <option value="0" <?= !$enabled ? 'selected' : '' ?>>Disabled</option>
                                </select>
                                <button type="submit" class="btn btn-small">Save</button>
                            </form>
                        <?php else: ?>
                            <p class="muted">Managed by platform team. Contact a master admin to request changes.</p>
                        <?php endif; ?>
                    </section>

                    <?php if ($extension->slug === 'platform/payfast'): ?>
                        <?php $sandbox = (bool) ($settings['sandbox_mode'] ?? false); ?>
                        <section class="extension-settings">
                            <h4>PayFast configuration</h4>
                            <form method="post" class="stacked-form">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="extension_slug" value="platform/payfast">
                                <label>
                                    Merchant ID
                                    <input type="text" name="merchant_id" value="<?= sanitize((string) ($settings['merchant_id'] ?? '')) ?>" required>
                                </label>
                                <label>
                                    Merchant Key
                                    <input type="password" name="merchant_key" placeholder="Enter new key">
                                    <?php if (!empty($settings['merchant_key'])): ?>
                                        <small class="muted">A key is already stored. Leave blank to keep the existing secret.</small>
                                    <?php endif; ?>
                                </label>
                                <label class="checkbox">
                                    <input type="checkbox" name="sandbox_mode" value="1" <?= $sandbox ? 'checked' : '' ?>>
                                    Enable sandbox mode
                                </label>
                                <button type="submit" class="btn btn-primary">Save PayFast settings</button>
                            </form>
                        </section>
                    <?php elseif ($extension->slug === 'platform/gtm'): ?>
                        <?php $enhanced = (bool) ($settings['enhanced_conversions'] ?? false); ?>
                        <section class="extension-settings">
                            <h4>Google Tag Manager</h4>
                            <form method="post" class="stacked-form">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="extension_slug" value="platform/gtm">
                                <label>
                                    Container ID
                                    <input type="text" name="container_id" value="<?= sanitize((string) ($settings['container_id'] ?? '')) ?>" placeholder="GTM-XXXXXXX" required>
                                </label>
                                <label class="checkbox">
                                    <input type="checkbox" name="enhanced_conversions" value="1" <?= $enhanced ? 'checked' : '' ?>>
                                    Send enhanced conversions (hashed PII)
                                </label>
                                <button type="submit" class="btn btn-primary">Save GTM settings</button>
                            </form>
                        </section>
                    <?php else: ?>
                        <section class="extension-settings">
                            <p class="muted">This extension does not have configurable settings yet.</p>
                        </section>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$title = 'Organization Extensions';
require view_path('layouts/base.php');
