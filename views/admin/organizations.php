<?php
/** @var array<int, \App\Models\Organization> $organizations */
/** @var array<string, \App\Models\User> $creators */
/** @var array{total:int,page:int,limit:int,total_pages:int,search:?string} $pagination */
/** @var array{values: array<string,string>, errors: array<string,string>} $form */
/** @var array{success: ?string} $flash */
/** @var string $csrfToken */

ob_start();
?>
<section class="admin-organizations">
    <header class="page-header">
        <h2>Organizations</h2>
        <p>Manage all tenant organizations across the platform.</p>
    </header>

    <?php if (!empty($flash['success'])): ?>
        <div class="alert success"><?= sanitize($flash['success']) ?></div>
    <?php endif; ?>

    <?php if (!empty($form['errors']['general'])): ?>
        <div class="alert error"><?= sanitize($form['errors']['general']) ?></div>
    <?php endif; ?>

    <div class="stat-grid">
        <article class="stat-card">
            <p class="label">Total Organizations</p>
            <p class="value"><?= number_format($pagination['total']) ?></p>
        </article>
        <article class="stat-card">
            <p class="label">Showing</p>
            <p class="value"><?= count($organizations) ?> / <?= $pagination['limit'] ?></p>
        </article>
    </div>

    <section class="panel">
        <form method="get" class="form-inline">
            <label for="search">Search</label>
            <input type="text" name="q" id="search" value="<?= sanitize($pagination['search'] ?? '') ?>" placeholder="Search by name or domain" />
            <button type="submit">Filter</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Domain</th>
                        <th>SSL Status</th>
                        <th>Created By</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($organizations === []): ?>
                        <tr>
                            <td colspan="5">No organizations found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($organizations as $organization): ?>
                            <?php $creator = $creators[$organization->createdBy] ?? null; ?>
                            <tr>
                                <td>
                                    <strong><?= sanitize($organization->name) ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($organization->customDomain)): ?>
                                        <?= sanitize($organization->customDomain) ?>
                                    <?php else: ?>
                                        <span class="muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge status-<?= sanitize($organization->sslCertificateStatus) ?>">
                                        <?= sanitize(ucwords($organization->sslCertificateStatus)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($creator !== null): ?>
                                        <?= sanitize($creator->name ?? $creator->email) ?>
                                    <?php else: ?>
                                        <span class="muted">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize(date('M j, Y', strtotime($organization->createdAt))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <nav class="pagination">
                <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                    <?php $query = http_build_query(['page' => $p, 'q' => $pagination['search']]); ?>
                    <a href="?<?= sanitize($query) ?>" class="<?= $p === $pagination['page'] ? 'active' : '' ?>">Page <?= $p ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Create Organization</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <div class="form-group">
                <label for="org_name">Organization Name</label>
                <input type="text" id="org_name" name="name" value="<?= sanitize($form['values']['name'] ?? '') ?>" required>
                <?php if (!empty($form['errors']['name'])): ?>
                    <small class="error-text"><?= sanitize($form['errors']['name']) ?></small>
                <?php endif; ?>
            </div>
            <div class="form-grid">
                <div>
                    <label for="primary_color">Primary Color</label>
                    <input type="text" id="primary_color" name="primary_color" value="<?= sanitize($form['values']['primary_color'] ?? '') ?>" placeholder="#0066CC">
                    <?php if (!empty($form['errors']['primary_color'])): ?>
                        <small class="error-text"><?= sanitize($form['errors']['primary_color']) ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="secondary_color">Secondary Color</label>
                    <input type="text" id="secondary_color" name="secondary_color" value="<?= sanitize($form['values']['secondary_color'] ?? '') ?>" placeholder="#F8F9FA">
                    <?php if (!empty($form['errors']['secondary_color'])): ?>
                        <small class="error-text"><?= sanitize($form['errors']['secondary_color']) ?></small>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="accent_color">Accent Color</label>
                    <input type="text" id="accent_color" name="accent_color" value="<?= sanitize($form['values']['accent_color'] ?? '') ?>" placeholder="#DC3545">
                    <?php if (!empty($form['errors']['accent_color'])): ?>
                        <small class="error-text"><?= sanitize($form['errors']['accent_color']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="custom_domain">Custom Domain</label>
                <input type="text" id="custom_domain" name="custom_domain" value="<?= sanitize($form['values']['custom_domain'] ?? '') ?>" placeholder="tenant.example.com">
                <?php if (!empty($form['errors']['custom_domain'])): ?>
                    <small class="error-text"><?= sanitize($form['errors']['custom_domain']) ?></small>
                <?php endif; ?>
            </div>
            <button type="submit">Create Organization</button>
        </form>
    </section>
</section>
<?php
$content = ob_get_clean();
$title = 'Organizations';
require view_path('layouts/base.php');
