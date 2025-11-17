<?php
ob_start();

$flash = $flash ?? ['success' => null, 'error' => null];
?>
<section class="org-properties">
    <header class="page-header">
        <div>
            <p class="page-eyebrow">Organization</p>
            <h2><?= sanitize($organization->name) ?> properties</h2>
            <p class="muted">Create, update, or archive listings that power your public storefront.</p>
        </div>
        <div class="cta-group">
            <a class="btn btn-primary" href="/org/property/create.php?id=<?= urlencode($organization->id) ?>">Add property</a>
        </div>
    </header>

    <?php if (!empty($flash['success'])): ?>
        <div class="alert alert-success"><?= sanitize($flash['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
        <div class="alert alert-danger"><?= sanitize($flash['error']) ?></div>
    <?php endif; ?>

    <?php if (empty($properties)): ?>
        <div class="empty-state">
            <p>No properties have been added for this organization yet.</p>
            <a class="btn btn-secondary" href="/org/property/create.php?id=<?= urlencode($organization->id) ?>">Create your first property</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Created</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $property): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($property->name) ?></strong>
                                <?php if (!empty($property->description)): ?>
                                    <p class="muted"><?= sanitize(mb_strimwidth($property->description, 0, 90, '…')) ?></p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($property->address)): ?>
                                    <?= sanitize($property->address) ?>
                                <?php else: ?>
                                    <span class="muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize(date('M j, Y', strtotime($property->createdAt))) ?></td>
                            <td class="actions">
                                <a class="btn btn-link" target="_blank" rel="noopener" href="/org/property/view.php?<?= sanitize(http_build_query(['org' => $property->organizationId, 'property' => $property->id])) ?>">Public view</a>
                                <a class="btn btn-link" href="/org/property/edit.php?id=<?= urlencode($organization->id) ?>&property=<?= urlencode($property->id) ?>">Edit</a>
                                <form method="post" action="/org/property/delete.php?id=<?= urlencode($organization->id) ?>" onsubmit="return confirm('Delete this property? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken ?? '') ?>">
                                    <input type="hidden" name="property_id" value="<?= sanitize($property->id) ?>">
                                    <button type="submit" class="btn btn-link text-danger">Delete</button>
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
$title = 'Properties';
require view_path('layouts/base.php');
