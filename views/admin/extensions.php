<?php
/** @var array<int, \App\Models\Extension> $extensions */
/** @var array<string, int> $enabledCounts */
/** @var int $totalOrganizations */
/** @var array{success: ?string, error: ?string} $flash */
/** @var string $csrfToken */

ob_start();
?>
<section class="panel">
    <header class="panel-header">
        <div>
            <p class="dashboard-eyebrow">Extensions</p>
            <h2>Extension Registry</h2>
            <p class="muted">Manage discovered extensions, review versions, and decide whether organization admins may toggle them.</p>
        </div>
        <div class="stat-chip">
            <span>Total organizations</span>
            <strong><?= sanitize((string) $totalOrganizations) ?></strong>
        </div>
    </header>

    <?php if ($flash['success']): ?>
        <div class="alert alert-success"><?= sanitize($flash['success']) ?></div>
    <?php endif; ?>
    <?php if ($flash['error']): ?>
        <div class="alert alert-danger"><?= sanitize($flash['error']) ?></div>
    <?php endif; ?>

    <?php if (empty($extensions)): ?>
        <p class="muted">No extensions have been registered yet. Upload a manifest and run <code>php cli/extensions.php sync</code>.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">Extension</th>
                    <th scope="col">Version</th>
                    <th scope="col">Status</th>
                    <th scope="col">Org exposure</th>
                    <th scope="col">Org admin control</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($extensions as $extension):
                    $enabledCount = $enabledCounts[$extension->id] ?? 0;
                    $statusClass = match ($extension->status) {
                        'active' => 'badge-success',
                        'installed' => 'badge-info',
                        'error' => 'badge-danger',
                        default => 'badge-muted',
                    };
                    ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?= sanitize($extension->displayName) ?></strong>
                            </div>
                            <p class="muted small-text">
                                <?= sanitize($extension->slug) ?>
                                <?php if ($extension->author): ?> · <?= sanitize($extension->author) ?><?php endif; ?>
                            </p>
                            <?php if ($extension->description): ?>
                                <p class="muted small-text"><?= sanitize($extension->description) ?></p>
                            <?php endif; ?>
                        </td>
                        <td><?= sanitize($extension->version) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= sanitize(ucfirst($extension->status)) ?></span></td>
                        <td>
                            <p class="muted small-text">Enabled in <?= sanitize((string) $enabledCount) ?> org<?= $enabledCount === 1 ? '' : 's' ?></p>
                        </td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                <input type="hidden" name="action" value="org_toggle_policy">
                                <input type="hidden" name="extension_id" value="<?= sanitize($extension->id) ?>">
                                <label class="sr-only" for="org-toggle-<?= sanitize($extension->id) ?>">Org admin control</label>
                                <select id="org-toggle-<?= sanitize($extension->id) ?>" name="allow_org_toggle">
                                    <option value="1" <?= $extension->allowOrgToggle ? 'selected' : '' ?>>Allowed</option>
                                    <option value="0" <?= !$extension->allowOrgToggle ? 'selected' : '' ?>>Restricted</option>
                                </select>
                                <button type="submit" class="btn btn-small">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
$title = 'Extension Registry';
require view_path('layouts/base.php');
